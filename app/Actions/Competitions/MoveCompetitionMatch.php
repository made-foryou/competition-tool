<?php

namespace App\Actions\Competitions;

use App\Enums\PlacementViolation;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Support\Scheduling\ClockTime;
use App\Support\Scheduling\GreedyScheduler;
use App\Support\Scheduling\MatchDaySchedule;
use App\Support\Scheduling\Placement;
use App\Support\Scheduling\PlacementValidator;
use App\Support\Scheduling\Slot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Zet één wedstrijd handmatig op een andere speeldag, tafel en tijdslot, en
 * zet hem daarmee vast: wie zelf een plek kiest, wil niet dat de eerstvolgende
 * herplanning hem weer weghaalt.
 *
 * De domeinvalidatie zit hier en niet in de Form Request. De Form Request
 * controleert alleen de vorm (bestaat de speeldag binnen deze competitie, hoort
 * de tafel bij die speeldag, is de tijd `H:i`); voor de harde randvoorwaarden
 * is de volledige planningscontext nodig, en die bouwen we één keer op — pas
 * nadat de rijlock op de competitie genomen is. Daardoor gebeurt de controle
 * onder exact dezelfde lock als het schrijven en kan een gelijktijdige
 * planner-run er niet tussen glippen met een plek die op het moment van kijken
 * nog vrij leek.
 *
 * De controle zelf loopt door dezelfde `PlacementValidator` als de planner,
 * zodat "hard" overal hetzelfde betekent: wat de planner nooit zou plaatsen,
 * kan een beheerder ook niet handmatig afdwingen. Te weinig rust is bewust wel
 * toegestaan — dat is een zachte regel — en komt als waarschuwing terug.
 */
class MoveCompetitionMatch
{
    public function __construct(
        private BuildSchedulingContext $buildSchedulingContext,
        private GreedyScheduler $greedyScheduler,
        private PlacementValidator $placementValidator,
    ) {}

    /**
     * Verplaatst de wedstrijd en zet hem vast.
     *
     * @return bool of de wedstrijd met een schending van de minimale rusttijd geplaatst is
     *
     * @throws ValidationException bij een harde schending, als veldfout op `starts_at`
     */
    public function handle(CompetitionMatch $match, int $matchDayId, int $fieldId, string $startsAt): bool
    {
        return DB::transaction(function () use ($match, $matchDayId, $fieldId, $startsAt): bool {
            // Zelfde rijlock als `ScheduleCompetitionMatches`: vanaf hier
            // werken we met de vergrendelde instantie, zodat de instellingen
            // en de bezetting vers zijn en een gelijktijdige planner-run niet
            // halverwege dezelfde plek inneemt.
            $locked = Competition::query()->whereKey($match->competition_id)->lockForUpdate()->firstOrFail();

            // Zonder de wedstrijd zelf: hij bezet zijn oude plek niet meer,
            // dus een verplaatsing binnen hetzelfde slot botst niet met zijn
            // eigen bezetting.
            $context = $this->buildSchedulingContext->handle($locked, excludeMatchId: $match->id);

            $matchDay = $context->matchDay($matchDayId);
            $slot = $matchDay?->grid->slotAt($startsAt);

            if (! $matchDay instanceof MatchDaySchedule || ! $slot instanceof Slot) {
                // Een speeldag zonder raster of een tijd die op geen enkele
                // slotgrens valt: dezelfde reden als een tijd buiten de
                // openingstijden, want het slot bestaat simpelweg niet.
                throw ValidationException::withMessages([
                    'starts_at' => $this->messageFor(PlacementViolation::OutsideOpeningHours),
                ]);
            }

            $placement = new Placement($matchDayId, $fieldId, $slot);

            $violation = $this->placementValidator->violation(
                $placement,
                $match->first_player_id,
                $match->second_player_id,
                $context,
            );

            if ($violation instanceof PlacementViolation) {
                throw ValidationException::withMessages(['starts_at' => $this->messageFor($violation)]);
            }

            // De rustschending komt uit de score van de planner, zodat
            // handmatig verplaatsen en automatisch plannen dezelfde definitie
            // van "te weinig rust" gebruiken.
            $restViolated = $this->greedyScheduler
                ->score($placement, $match->first_player_id, $match->second_player_id, $context)
                ->restViolated;

            // Via de query builder: de planningskolommen zijn bewust niet
            // mass-assignable, en daardoor passeren de mutators van het model
            // niet -- dus zetten we de kloktijd zelf als `H:i:s`, zoals de
            // planner dat ook doet.
            CompetitionMatch::query()
                ->whereKey($match->id)
                ->update([
                    'match_day_id' => $matchDayId,
                    'match_day_field_id' => $fieldId,
                    'starts_at' => $slot->startsAt().':00',
                    'ends_at' => ClockTime::fromMinutes($matchDay->grid->matchEndMinute($slot)).':00',
                    'pinned_at' => now(),
                    'scheduling_failure' => null,
                ]);

            return $restViolated;
        });
    }

    /**
     * De tekst per harde schending. Eén plek, zodat een nieuwe schending
     * meteen zichtbaar wordt als ontbrekende `match`-tak.
     */
    private function messageFor(PlacementViolation $violation): string
    {
        return match ($violation) {
            PlacementViolation::FieldNotOnMatchDay => __('This field does not belong to the selected match day.'),
            PlacementViolation::OutsideOpeningHours => __('This time is not a slot within the opening hours of this match day.'),
            PlacementViolation::PlayerUnavailable => __('One of the players is not available on this match day.'),
            PlacementViolation::MaxMatchesReached => __('One of the players already plays the maximum number of matches on this match day.'),
            PlacementViolation::PlayerBusy => __('One of the players already plays another match at this time.'),
            PlacementViolation::TableOccupied => __('This field is already taken at this time.'),
        };
    }
}
