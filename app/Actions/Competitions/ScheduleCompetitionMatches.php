<?php

namespace App\Actions\Competitions;

use App\Concerns\DeterminesSchedulingBlocker;
use App\Enums\MatchStatus;
use App\Enums\SchedulingBlocker;
use App\Enums\SchedulingMode;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Support\Scheduling\ClockTime;
use App\Support\Scheduling\GreedyScheduler;
use App\Support\Scheduling\MatchDaySchedule;
use App\Support\Scheduling\PendingMatch;
use App\Support\Scheduling\SchedulingContext;
use App\Support\Scheduling\SchedulingResult;
use App\Support\Scheduling\SchedulingSolution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Verdeelt de openstaande wedstrijden van een competitie over speeldagen,
 * tafels en tijdsloten.
 *
 * Twee smaken. **Aanvullen** (`SchedulingMode::Fill`) laat het bestaande
 * schema staan en vult alleen de wedstrijden aan die nog geen volledige plek
 * hebben — stabiel voor deelnemers die hun avond al weten. **Opnieuw plannen**
 * (`SchedulingMode::Reschedule`) maakt eerst alle openstaande, niet-vastgezette
 * wedstrijden leeg en rekent het hele schema opnieuw uit.
 *
 * "Volledig gepland" betekent speeldag én tafel én begintijd. Een wedstrijd
 * waarvan de tafel achteraf verdwenen is, telt dus als ongepland en wordt bij
 * aanvullen gewoon opnieuw meegenomen.
 *
 * Gespeelde en vastgezette wedstrijden bewegen nooit, in geen van beide
 * smaken; ze bezetten wel hun tafel en tijd en tellen mee voor de rust en het
 * dagmaximum van beide spelers.
 *
 * Het resultaat is geen exception maar een uitkomst: een competitie zonder
 * speeldagen, tafels, beschikbaarheid of wedstrijden is een normale toestand
 * onderweg naar een volle competitie, en komt terug als een geblokkeerd
 * `SchedulingResult` waarbij niets geschreven is.
 *
 * Deze Action controleert `CompetitionStatus::allowsScheduling()` bewust
 * níet: die statusguard hoort in de HTTP-laag, bij de route en de Form
 * Request, en moet daar dus expliciet worden toegevoegd.
 */
class ScheduleCompetitionMatches
{
    use DeterminesSchedulingBlocker;

    public function __construct(
        private BuildSchedulingContext $buildSchedulingContext,
        private GreedyScheduler $greedyScheduler,
    ) {}

    public function handle(Competition $competition, SchedulingMode $mode = SchedulingMode::Fill): SchedulingResult
    {
        return DB::transaction(function () use ($competition, $mode): SchedulingResult {
            // Zelfde rijlock als SyncCompetitionMatches: plannen leest de
            // deelnemers, hun beschikbaarheid en de hele wedstrijdenlijst, dus
            // een gelijktijdige deelnemersmutatie of een tweede planner-run mag
            // er niet halverwege doorheen fietsen. Vanaf hier werken we met de
            // vergrendelde instantie: de lock beschermt tegen gelijktijdig
            // schrijven, niet tegen een verouderde instance van de aanroeper,
            // dus ook `settings` lezen we onder de lock opnieuw.
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();

            $blocker = $this->schedulingBlocker($locked, $mode);

            if ($blocker instanceof SchedulingBlocker) {
                return SchedulingResult::blocked($mode, $blocker);
            }

            if ($mode === SchedulingMode::Reschedule) {
                $this->releasePendingMatches($locked);
            }

            $context = $this->buildSchedulingContext->handle($locked);

            // Het totaal na de eventuele release-stap: wat deze ronde niet
            // geplaatst of als mislukt gemarkeerd wordt, telt als onaangeroerd.
            // Zo dekken de drie tellers samen altijd de volledige
            // wedstrijdenlijst — ook een gespeelde wedstrijd waarvan de tafel
            // inmiddels verwijderd is.
            $totalCount = CompetitionMatch::query()
                ->where('competition_id', $locked->id)
                ->count();

            $solution = $this->greedyScheduler->schedule($context, $this->pendingMatches($locked));

            // Eerst de mislukkingen: die maken hun oude plek leeg, zodat een
            // stale (tafel, begintijd) de unique-index niet in de weg zit
            // wanneer een andere wedstrijd hem meteen daarna inneemt.
            $this->storeFailures($solution);
            $this->storePlacements($solution, $context);

            return new SchedulingResult(
                mode: $mode,
                scheduledCount: $solution->scheduledCount(),
                keptCount: $totalCount - $solution->scheduledCount() - $solution->unscheduledCount(),
                unscheduledCount: $solution->unscheduledCount(),
                failures: $solution->failures(),
                restViolations: $solution->restViolations(),
            );
        });
    }

    /**
     * Maakt de planning van alle openstaande, niet-vastgezette wedstrijden
     * leeg. `pinned_at` blijft bewust staan: vastzetten is een keuze van de
     * beheerder en overleeft het opnieuw plannen.
     */
    private function releasePendingMatches(Competition $competition): void
    {
        CompetitionMatch::query()
            ->where('competition_id', $competition->id)
            ->where('status', MatchStatus::Pending->value)
            ->whereNull('pinned_at')
            ->update([
                'match_day_id' => null,
                'match_day_field_id' => null,
                'starts_at' => null,
                'ends_at' => null,
                'scheduling_failure' => null,
            ]);
    }

    /**
     * De openstaande wedstrijden zonder volledige plek, op id zodat de
     * planner altijd dezelfde invoervolgorde krijgt.
     *
     * @return list<PendingMatch>
     */
    private function pendingMatches(Competition $competition): array
    {
        return array_values(CompetitionMatch::query()
            ->where('competition_id', $competition->id)
            ->where('status', MatchStatus::Pending->value)
            ->where(fn (Builder $query) => $query
                ->whereNull('match_day_id')
                ->orWhereNull('match_day_field_id')
                ->orWhereNull('starts_at'))
            ->orderBy('id')
            ->get(['id', 'first_player_id', 'second_player_id'])
            ->map(fn (CompetitionMatch $match): PendingMatch => new PendingMatch(
                id: $match->id,
                firstPlayerId: $match->first_player_id,
                secondPlayerId: $match->second_player_id,
            ))
            ->all());
    }

    /**
     * Schrijft de gevonden plekken weg. Via de query builder, omdat de
     * planningskolommen bewust niet mass-assignable zijn — en daardoor
     * passeren de mutators van het model niet, dus zetten we de kloktijd zelf
     * als `H:i:s` (zoals `FormatsClockTime` doet) zodat MySQL en SQLite exact
     * dezelfde waarde te zien krijgen.
     *
     * @throws LogicException als een plaatsing naar een speeldag wijst die
     *                        niet in de context zit; dan klopt de uitkomst van
     *                        de planner niet en mag er niets weggeschreven
     *                        worden
     */
    private function storePlacements(SchedulingSolution $solution, SchedulingContext $context): void
    {
        foreach ($solution->placements() as $matchId => $placement) {
            $matchDay = $context->matchDay($placement->matchDayId);

            if (! $matchDay instanceof MatchDaySchedule) {
                throw new LogicException('The planner placed match '.$matchId.' on match day '.$placement->matchDayId.', which is not part of the scheduling context.');
            }

            $endMinute = $matchDay->grid->matchEndMinute($placement->slot);

            CompetitionMatch::query()
                ->whereKey($matchId)
                ->update([
                    'match_day_id' => $placement->matchDayId,
                    'match_day_field_id' => $placement->fieldId,
                    'starts_at' => $placement->slot->startsAt().':00',
                    'ends_at' => ClockTime::fromMinutes($endMinute).':00',
                    'scheduling_failure' => null,
                ]);
        }
    }

    /**
     * Legt per reden vast welke wedstrijden geen plek kregen, en maakt hun
     * planning leeg: een mislukte wedstrijd mag geen halve plek overhouden.
     * Gegroepeerd per reden, zodat er hooguit één update per reden nodig is.
     */
    private function storeFailures(SchedulingSolution $solution): void
    {
        /** @var array<string, list<int>> $matchIdsByReason */
        $matchIdsByReason = [];

        foreach ($solution->failures() as $matchId => $reason) {
            $matchIdsByReason[$reason->value][] = $matchId;
        }

        foreach ($matchIdsByReason as $reason => $matchIds) {
            CompetitionMatch::query()
                ->whereIn('id', $matchIds)
                ->update([
                    'match_day_id' => null,
                    'match_day_field_id' => null,
                    'starts_at' => null,
                    'ends_at' => null,
                    'scheduling_failure' => $reason,
                ]);
        }
    }
}
