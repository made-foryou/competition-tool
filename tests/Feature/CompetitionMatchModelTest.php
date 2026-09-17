<?php

use App\Enums\SchedulingFailure;
use App\Models\CompetitionMatch;
use App\Models\MatchDay;
use App\Models\MatchDayField;

test('the schedule times stay H:i and are null for an unscheduled match', function () {
    $matchDay = MatchDay::factory()->create();
    $field = MatchDayField::factory()->create(['match_day_id' => $matchDay]);

    $unscheduled = CompetitionMatch::factory()->create();
    $scheduled = CompetitionMatch::factory()->scheduled($matchDay, $field, '19:00')->create();

    expect($unscheduled->fresh()->starts_at)->toBeNull()
        ->and($unscheduled->fresh()->ends_at)->toBeNull()
        ->and($scheduled->fresh()->starts_at)->toBe('19:00')
        ->and($scheduled->fresh()->ends_at)->toBe('19:20');
});

test('a match is scheduled once match day field and start time are all set', function () {
    $matchDay = MatchDay::factory()->create();
    $field = MatchDayField::factory()->create(['match_day_id' => $matchDay]);

    $scheduled = CompetitionMatch::factory()->scheduled($matchDay, $field, '19:00')->create();
    $withoutField = CompetitionMatch::factory()->create(['match_day_id' => $matchDay]);

    expect($scheduled->isScheduled())->toBeTrue()
        ->and($withoutField->isScheduled())->toBeFalse();
});

test('a match is locked when it is played or pinned', function () {
    $played = CompetitionMatch::factory()->played()->create();
    $pinned = CompetitionMatch::factory()->pinned()->create();
    $ordinary = CompetitionMatch::factory()->create();

    expect($played->isLocked())->toBeTrue()
        ->and($pinned->isLocked())->toBeTrue()
        ->and($pinned->isPinned())->toBeTrue()
        ->and($ordinary->isLocked())->toBeFalse()
        ->and($ordinary->isPinned())->toBeFalse();
});

test('the scheduling failure is cast to its enum', function () {
    $match = CompetitionMatch::factory()->unschedulable(SchedulingFailure::NoCapacity)->create();

    expect($match->fresh()->scheduling_failure)->toBe(SchedulingFailure::NoCapacity)
        ->and($match->fresh()->match_day_id)->toBeNull()
        ->and($match->fresh()->starts_at)->toBeNull();
});

test('the scheduled factory state derives the end time from the duration', function () {
    $matchDay = MatchDay::factory()->create();
    $field = MatchDayField::factory()->create(['match_day_id' => $matchDay]);

    $match = CompetitionMatch::factory()->scheduled($matchDay, $field, '20:45', durationMinutes: 30)->create();

    expect($match->fresh()->ends_at)->toBe('21:15');
});
