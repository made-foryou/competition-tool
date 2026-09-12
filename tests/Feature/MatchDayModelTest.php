<?php

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayField;
use Carbon\CarbonImmutable;

test('a match day belongs to a competition and returns its fields in order', function () {
    $matchDay = MatchDay::factory()->create();

    MatchDayField::factory()->create(['match_day_id' => $matchDay, 'name' => 'Tweede', 'position' => 2]);
    MatchDayField::factory()->create(['match_day_id' => $matchDay, 'name' => 'Eerste', 'position' => 1]);

    expect($matchDay->competition)->toBeInstanceOf(Competition::class)
        ->and($matchDay->fields()->pluck('name')->all())->toBe(['Eerste', 'Tweede']);
});

test('the date is cast and the times stay H:i', function () {
    $matchDay = MatchDay::factory()->create([
        'date' => '2026-10-02',
        'starts_at' => '08:30',
        'ends_at' => '12:45',
    ]);

    expect($matchDay->date)->toBeInstanceOf(CarbonImmutable::class)
        ->and($matchDay->fresh()->starts_at)->toBe('08:30')
        ->and($matchDay->fresh()->ends_at)->toBe('12:45');
});

test('a competition returns its match days ordered by date and start time', function () {
    $competition = Competition::factory()->create();

    MatchDay::factory()->create(['competition_id' => $competition, 'date' => '2026-10-03', 'starts_at' => '09:00']);
    MatchDay::factory()->create(['competition_id' => $competition, 'date' => '2026-10-02', 'starts_at' => '13:00']);
    MatchDay::factory()->create(['competition_id' => $competition, 'date' => '2026-10-02', 'starts_at' => '09:00']);

    expect($competition->matchDays()->get()->map(
        fn (MatchDay $matchDay): string => $matchDay->date->toDateString().' '.$matchDay->starts_at
    )->all())->toBe(['2026-10-02 09:00', '2026-10-02 13:00', '2026-10-03 09:00']);
});

test('deleting a competition removes its match days and their fields', function () {
    $competition = Competition::factory()->create();
    MatchDay::factory()->withFields(3)->create(['competition_id' => $competition]);

    $competition->delete();

    expect(MatchDay::query()->count())->toBe(0)
        ->and(MatchDayField::query()->count())->toBe(0);
});
