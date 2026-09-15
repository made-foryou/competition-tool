<?php

namespace App\Actions\Competitions;

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Support\Scheduling\AvailabilityLookup;
use App\Support\Scheduling\ClockTime;
use App\Support\Scheduling\MatchDaySchedule;
use App\Support\Scheduling\ScheduleBoard;
use App\Support\Scheduling\SchedulingContext;
use App\Support\Scheduling\SlotGrid;

/**
 * Leest de database uit en levert de planner zijn context: de instellingen,
 * de speeldagen met hun tafels en slotraster, de beschikbaarheid van de
 * huidige deelnemers en de bezetting door de wedstrijden die al een plek
 * hebben.
 *
 * Los van `ScheduleCompetitionMatches` omdat ook het handmatig verplaatsen
 * van één wedstrijd (latere fase) dezelfde context nodig heeft — dan met
 * `$excludeMatchId`, zodat de wedstrijd die verplaatst wordt zichzelf niet in
 * de weg zit.
 */
class BuildSchedulingContext
{
    public function handle(Competition $competition, ?int $excludeMatchId = null): SchedulingContext
    {
        $settings = $competition->settings;

        // De relatie sorteert al op datum en begintijd; het id is de derde
        // sleutel, zodat twee speeldagen op hetzelfde moment toch een vaste
        // volgorde hebben en het resultaat deterministisch blijft.
        $matchDays = $competition->matchDays()->with('fields')->orderBy('id')->get();

        $schedules = $matchDays->map(function (MatchDay $matchDay) use ($settings): MatchDaySchedule {
            /** @var list<int> $fieldIds */
            $fieldIds = $matchDay->fields->pluck('id')->all();

            return new MatchDaySchedule(
                matchDayId: $matchDay->id,
                date: $matchDay->date->toDateString(),
                startsAt: $matchDay->starts_at,
                fieldIds: $fieldIds,
                grid: SlotGrid::for($matchDay->starts_at, $matchDay->ends_at, $settings),
            );
        })->all();

        return new SchedulingContext(
            settings: $settings,
            matchDays: array_values($schedules),
            availability: $this->availabilityFor($competition, array_values($matchDays->modelKeys())),
            board: $this->boardFor($competition, $excludeMatchId),
        );
    }

    /**
     * De ingevulde beschikbaarheid op deze speeldagen, beperkt tot de
     * huidige deelnemers: rijen van iemand die de competitie inmiddels
     * verlaten heeft mogen de planner niet beïnvloeden.
     *
     * @param  list<int|string>  $matchDayIds
     */
    private function availabilityFor(Competition $competition, array $matchDayIds): AvailabilityLookup
    {
        /** @var array<int, list<int>> $availableByMatchDay */
        $availableByMatchDay = MatchDayAvailability::query()
            ->whereIn('match_day_id', $matchDayIds)
            ->whereIn('user_id', $competition->participants()->select('users.id'))
            ->orderBy('user_id')
            ->get(['match_day_id', 'user_id'])
            ->groupBy('match_day_id')
            ->map(fn ($rows): array => array_values($rows->pluck('user_id')->all()))
            ->all();

        return new AvailabilityLookup($availableByMatchDay);
    }

    /**
     * De bezetting door de wedstrijden die al een plek hebben.
     *
     * Bezetting gaat als tijdsinterval het bord op en niet als slot-index:
     * een gespeelde of vastgezette wedstrijd die na een wijziging van de
     * instellingen of de openingstijden niet meer op het huidige raster ligt,
     * blokkeert zo nog steeds precies de minuten die hij bezet houdt. Een
     * gespeelde wedstrijd waarvan de tafel verwijderd is, blokkeert alleen
     * zijn spelers en geen tafel.
     */
    private function boardFor(Competition $competition, ?int $excludeMatchId): ScheduleBoard
    {
        $board = new ScheduleBoard;

        $matches = $competition->matches()
            ->whereNotNull('match_day_id')
            ->whereNotNull('starts_at')
            ->when($excludeMatchId !== null, fn ($query) => $query->whereKeyNot($excludeMatchId))
            ->get();

        foreach ($matches as $match) {
            if (! $match->isScheduled() && ! $match->isPlayed()) {
                continue;
            }

            $matchDayId = $match->match_day_id;
            $startsAt = $match->starts_at;

            if ($matchDayId === null || $startsAt === null) {
                continue;
            }

            $startMinute = ClockTime::toMinutes($startsAt);
            $endMinute = $match->ends_at === null
                ? $startMinute + $competition->settings->matchDurationMinutes
                : ClockTime::toMinutes($match->ends_at);

            $board->occupyPlayer($match->first_player_id, $matchDayId, $startMinute, $endMinute);
            $board->occupyPlayer($match->second_player_id, $matchDayId, $startMinute, $endMinute);

            if ($match->match_day_field_id !== null) {
                // Een tafel blijft bezet tot en met de wisseltijd.
                $board->occupyTable(
                    $matchDayId,
                    $match->match_day_field_id,
                    $startMinute,
                    $endMinute + $competition->settings->bufferMinutes,
                );
            }
        }

        return $board;
    }
}
