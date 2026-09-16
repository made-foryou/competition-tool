<?php

namespace App\Concerns;

use App\Enums\MatchStatus;
use App\Enums\SchedulingMode;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\MatchDay;
use App\Models\MatchDayField;
use App\Support\Scheduling\ClockTime;
use App\Support\Scheduling\Slot;
use App\Support\Scheduling\SlotGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Levert alles wat de tab "Schema" op de competitiepagina nodig heeft: of er
 * gepland mag worden en zo niet waarom, de tellers, het raster per speeldag
 * met de wedstrijden erop, en het rapport (niet ingepland + rust niet
 * gehaald).
 *
 * Het raster komt uit dezelfde `SlotGrid` als de planner gebruikt, zodat het
 * scherm nooit een andere indeling toont dan waarop gepland is. De
 * rustschendingen worden om dezelfde reden niet uit het planningsresultaat
 * overgenomen maar uit de opgeslagen tijden herleid: het rapport moet een
 * refresh overleven en ook kloppen na een handmatige verplaatsing.
 */
trait SummarizesSchedule
{
    use DeterminesSchedulingBlocker;
    use SummarizesMatchDay;

    /**
     * De props van de tab "Schema".
     *
     * `blocked_reason` volgt exact de volgorde die de beheerder bij het
     * indrukken van de knop te zien krijgt: eerst de statuscheck uit
     * `CompetitionScheduleController` (`'inactive'`), daarna de precondities
     * uit `DeterminesSchedulingBlocker`. Beide kanten lezen dus dezelfde
     * bron; de hint op de uitgeschakelde knop en de toast kunnen niet
     * uiteenlopen.
     *
     * `status` staat er los van: `blocked_reason` zegt alleen dát er niet
     * gepland mag worden, terwijl het scherm bij een afgeronde competitie een
     * ander verhaal moet vertellen dan bij een concept ("pas de status aan"
     * slaat op een afgeronde competitie nergens op).
     *
     * @return array{
     *     can_schedule: bool,
     *     blocked_reason: 'inactive'|'no_match_days'|'no_fields'|'no_availability'|'no_matches'|'nothing_to_schedule'|null,
     *     status: 'draft'|'active'|'finished',
     *     summary: array{total: int, scheduled: int, unscheduled: int, played: int, pinned: int},
     *     match_days: list<array{
     *         id: int,
     *         date: string,
     *         starts_at: string,
     *         ends_at: string,
     *         fields: list<array{id: int, name: string, position: int}>,
     *         slots: list<array{index: int, starts_at: string, ends_at: string}>,
     *         break: array{starts_at: string, ends_at: string}|null,
     *         matches: list<array{
     *             id: int,
     *             field_id: int|null,
     *             starts_at: string|null,
     *             ends_at: string|null,
     *             first_player: string,
     *             second_player: string,
     *             status: string,
     *             is_pinned: bool,
     *             slot_index: int|null,
     *         }>,
     *     }>,
     *     unscheduled: list<array{id: int, first_player: string, second_player: string, reason: string|null}>,
     *     rest_violations: list<array{match_id: int, player: string, gap_minutes: int, overlapping: bool}>,
     * }
     */
    protected function scheduleProps(Competition $competition): array
    {
        $blockedReason = $competition->status->allowsScheduling() === false
            ? 'inactive'
            : $this->schedulingBlocker($competition, SchedulingMode::Fill)?->value;

        // Eén ronde voor het hele grid: de speeldagen met hun tafels en hun
        // wedstrijden inclusief beide spelers. Zonder deze eager loads levert
        // een competitie met tien speeldagen honderden losse queries op.
        $matchDays = $competition->matchDays()
            ->with(['fields', 'matches.firstPlayer', 'matches.secondPlayer'])
            ->orderBy('id')
            ->get();

        return [
            'can_schedule' => $blockedReason === null,
            'blocked_reason' => $blockedReason,
            'status' => $competition->status->value,
            'summary' => $this->scheduleSummary($competition),
            'match_days' => array_values($matchDays
                ->map(fn (MatchDay $matchDay): array => $this->matchDayScheduleProps($competition, $matchDay))
                ->all()),
            'unscheduled' => $this->unscheduledRows($competition),
            'rest_violations' => $this->restViolationRows($competition, array_values($matchDays->all())),
        ];
    }

    /**
     * De tellers boven het grid. Bewust losse `count()`-queries in plaats van
     * één opgehaalde collectie: de wedstrijdenlijst van een round robin loopt
     * in de honderden en voor een teller hoeft geen enkel model gehydrateerd
     * te worden. De voorwaarden zijn de querytegenhangers van
     * `CompetitionMatch::isScheduled()` en `isPinned()`.
     *
     * @return array{total: int, scheduled: int, unscheduled: int, played: int, pinned: int}
     */
    private function scheduleSummary(Competition $competition): array
    {
        return [
            'total' => $competition->matches()->count(),
            'scheduled' => $competition->matches()
                ->whereNotNull('match_day_id')
                ->whereNotNull('match_day_field_id')
                ->whereNotNull('starts_at')
                ->count(),
            'unscheduled' => $this->unscheduledMatchesQuery($competition)->count(),
            'played' => $competition->matches()->where('status', MatchStatus::Played->value)->count(),
            'pinned' => $competition->matches()->whereNotNull('pinned_at')->count(),
        ];
    }

    /**
     * De wedstrijden die nog een plek moeten krijgen: openstaand en niet
     * volledig ingepland. Dezelfde definitie als
     * `ScheduleCompetitionMatches` hanteert, zodat de teller en de lijst in
     * het rapport precies de wedstrijden noemen die een volgende ronde
     * oppakt.
     *
     * @return HasMany<CompetitionMatch, Competition>
     */
    private function unscheduledMatchesQuery(Competition $competition): HasMany
    {
        return $competition->matches()
            ->where('status', MatchStatus::Pending->value)
            ->where(fn (Builder $query) => $query
                ->whereNull('match_day_id')
                ->orWhereNull('match_day_field_id')
                ->orWhereNull('starts_at'));
    }

    /**
     * Het rapportonderdeel "Niet ingepland". `reason` is `null` zolang de
     * planner deze wedstrijd nooit heeft geprobeerd (nieuw toegevoegde
     * deelnemer); de UI maakt daar een eigen tekst van.
     *
     * @return list<array{id: int, first_player: string, second_player: string, reason: string|null}>
     */
    private function unscheduledRows(Competition $competition): array
    {
        return array_values($this->unscheduledMatchesQuery($competition)
            ->with(['firstPlayer', 'secondPlayer'])
            ->get()
            ->map(fn (CompetitionMatch $match): array => [
                'id' => $match->id,
                'first_player' => $match->firstPlayer->display_name,
                'second_player' => $match->secondPlayer->display_name,
                'reason' => $match->scheduling_failure?->value,
            ])
            ->all());
    }

    /**
     * Eén speeldag: het slotraster, de tafels als kolommen en alle
     * wedstrijden die op deze dag staan (ook de gespeelde).
     *
     * `slot_index` is `null` zodra een wedstrijd niet meer op een slotgrens
     * van het huidige raster begint — dat gebeurt wanneer de instellingen of
     * de openingstijden na het plannen zijn gewijzigd. De UI zet zulke
     * wedstrijden in een aparte lijst onder het grid in plaats van ze te
     * verbergen.
     *
     * @return array{
     *     id: int,
     *     date: string,
     *     starts_at: string,
     *     ends_at: string,
     *     fields: list<array{id: int, name: string, position: int}>,
     *     slots: list<array{index: int, starts_at: string, ends_at: string}>,
     *     break: array{starts_at: string, ends_at: string}|null,
     *     matches: list<array{
     *         id: int,
     *         field_id: int|null,
     *         starts_at: string|null,
     *         ends_at: string|null,
     *         first_player: string,
     *         second_player: string,
     *         status: string,
     *         is_pinned: bool,
     *         slot_index: int|null,
     *     }>,
     * }
     */
    private function matchDayScheduleProps(Competition $competition, MatchDay $matchDay): array
    {
        $grid = SlotGrid::for($matchDay->starts_at, $matchDay->ends_at, $competition->settings);
        $breakStartsAt = $grid->breakStartsAt();
        $breakEndsAt = $grid->breakEndsAt();

        return [
            ...$this->matchDayProps($matchDay),
            'fields' => array_values($matchDay->fields
                ->map(fn (MatchDayField $field): array => [
                    'id' => $field->id,
                    'name' => $field->name,
                    'position' => $field->position,
                ])
                ->all()),
            // De eindtijd van een slot is hier bewust de eindtijd van de
            // wedstrijd erop (zonder wisseltijd), zodat de rij in het grid
            // dezelfde tijden toont als de wedstrijd die erin staat.
            'slots' => array_map(fn (Slot $slot): array => [
                'index' => $slot->index,
                'starts_at' => $slot->startsAt(),
                'ends_at' => ClockTime::fromMinutes($grid->matchEndMinute($slot)),
            ], $grid->slots),
            'break' => $breakStartsAt === null || $breakEndsAt === null
                ? null
                : ['starts_at' => $breakStartsAt, 'ends_at' => $breakEndsAt],
            'matches' => array_values($matchDay->matches
                ->map(fn (CompetitionMatch $match): array => [
                    'id' => $match->id,
                    'field_id' => $match->match_day_field_id,
                    'starts_at' => $match->starts_at,
                    'ends_at' => $match->ends_at,
                    'first_player' => $match->firstPlayer->display_name,
                    'second_player' => $match->secondPlayer->display_name,
                    'status' => $match->status->value,
                    'is_pinned' => $match->isPinned(),
                    'slot_index' => $match->starts_at === null ? null : $grid->slotAt($match->starts_at)?->index,
                ])
                ->all()),
        ];
    }

    /**
     * Het rapportonderdeel "Rust niet gehaald", herleid uit de opgeslagen
     * tijden in plaats van uit het planningsresultaat: zo overleeft het
     * rapport een refresh en klopt het ook na een handmatige verplaatsing.
     *
     * Per speeldag en per speler worden de eigen wedstrijden op volgorde
     * gezet; elk opeenvolgend paar met minder dan `min_rest_minutes` ertussen
     * levert één melding op bij de látere wedstrijd — dat is de wedstrijd die
     * de beheerder kan verplaatsen om het gat te repareren. Een wedstrijd
     * zonder eindtijd levert geen berekenbaar gat op en slaat het paar over.
     *
     * Twee wedstrijden kunnen elkaar overlappen (handmatig gezet of al
     * gespeeld). Een negatief gat is voor de lezer geen bruikbaar getal, dus
     * `gap_minutes` wordt op 0 geklemd en `overlapping` vertelt de UI dat er
     * een andere zin bij hoort.
     *
     * @param  list<MatchDay>  $matchDays  in schermvolgorde, met `matches`, `firstPlayer` en `secondPlayer` al geladen
     * @return list<array{match_id: int, player: string, gap_minutes: int, overlapping: bool}>
     */
    private function restViolationRows(Competition $competition, array $matchDays): array
    {
        $minRestMinutes = $competition->settings->minRestMinutes;
        $rows = [];

        foreach ($matchDays as $matchDay) {
            /** @var array<int, list<CompetitionMatch>> $matchesByPlayer */
            $matchesByPlayer = [];

            foreach ($matchDay->matches as $match) {
                if ($match->starts_at === null) {
                    continue;
                }

                $matchesByPlayer[$match->first_player_id][] = $match;
                $matchesByPlayer[$match->second_player_id][] = $match;
            }

            ksort($matchesByPlayer);

            foreach ($matchesByPlayer as $playerId => $matches) {
                usort($matches, fn (CompetitionMatch $first, CompetitionMatch $second): int => [$first->starts_at, $first->id] <=> [$second->starts_at, $second->id]);

                $playerRows = [];

                for ($index = 1; $index < count($matches); $index++) {
                    $previous = $matches[$index - 1];
                    $current = $matches[$index];

                    if ($previous->ends_at === null || $current->starts_at === null) {
                        continue;
                    }

                    $gapMinutes = ClockTime::toMinutes($current->starts_at) - ClockTime::toMinutes($previous->ends_at);

                    if ($gapMinutes >= $minRestMinutes) {
                        continue;
                    }

                    $playerRows[] = [
                        'match_id' => $current->id,
                        'player' => $current->first_player_id === $playerId
                            ? $current->firstPlayer->display_name
                            : $current->secondPlayer->display_name,
                        'gap_minutes' => max(0, $gapMinutes),
                        'overlapping' => $gapMinutes < 0,
                    ];
                }

                usort($playerRows, fn (array $first, array $second): int => $first['match_id'] <=> $second['match_id']);

                $rows = [...$rows, ...$playerRows];
            }
        }

        return $rows;
    }
}
