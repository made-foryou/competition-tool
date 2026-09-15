<?php

use App\Support\CompetitionSettings;
use App\Support\Scheduling\AvailabilityLookup;
use App\Support\Scheduling\MatchDaySchedule;
use App\Support\Scheduling\ScheduleBoard;
use App\Support\Scheduling\SchedulingContext;
use App\Support\Scheduling\SlotGrid;

/**
 * Planningsinstellingen zonder poules; de planner gebruikt die toch niet.
 * Wordt gedeeld door de unittests in deze map, omdat testbestanden hun
 * functies in dezelfde globale ruimte zetten.
 */
function settings(int $duration = 20, int $buffer = 5, int $rest = 10, int $break = 15, int $max = 0): CompetitionSettings
{
    return new CompetitionSettings(
        matchDurationMinutes: $duration,
        bufferMinutes: $buffer,
        minRestMinutes: $rest,
        breakDurationMinutes: $break,
        usePools: false,
        poolSize: null,
        maxMatchesPerPlayerPerDay: $max,
    );
}

/**
 * Bouwt een context uit compacte arrays: speeldagen met hun openingstijden en
 * tafels, plus per speeldag de beschikbare spelers.
 *
 * @param  array<int, array{starts_at?: string, ends_at?: string, date?: string, fields?: list<int>}>  $days
 * @param  array<int, list<int>>  $availability
 */
function schedulingContext(array $days, array $availability, ?CompetitionSettings $settings = null, ?ScheduleBoard $board = null): SchedulingContext
{
    $settings ??= settings();

    $matchDays = [];
    $position = 1;

    foreach ($days as $matchDayId => $day) {
        $startsAt = $day['starts_at'] ?? '19:00';

        $matchDays[] = new MatchDaySchedule(
            matchDayId: $matchDayId,
            date: $day['date'] ?? sprintf('2026-10-%02d', $position),
            startsAt: $startsAt,
            fieldIds: $day['fields'] ?? [1],
            grid: SlotGrid::for($startsAt, $day['ends_at'] ?? '23:00', $settings),
        );

        $position++;
    }

    return new SchedulingContext(
        settings: $settings,
        matchDays: $matchDays,
        availability: new AvailabilityLookup($availability),
        board: $board ?? new ScheduleBoard,
    );
}
