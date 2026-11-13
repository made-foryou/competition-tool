<?php

use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\MatchDayField;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

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

test('deleting a match day clears the schedule of its pending matches', function () {
    $matchDay = MatchDay::factory()->create();
    $field = MatchDayField::factory()->create(['match_day_id' => $matchDay]);

    $pending = CompetitionMatch::factory()->scheduled($matchDay, $field, '19:00')->create();
    $pinned = CompetitionMatch::factory()->scheduled($matchDay, $field, '19:25')->pinned()->create();

    $matchDay->delete();

    foreach ([$pending, $pinned] as $match) {
        $fresh = $match->fresh();

        expect($fresh->match_day_id)->toBeNull()
            ->and($fresh->match_day_field_id)->toBeNull()
            ->and($fresh->starts_at)->toBeNull()
            ->and($fresh->ends_at)->toBeNull()
            ->and($fresh->pinned_at)->toBeNull()
            ->and($fresh->scheduling_failure)->toBeNull();
    }

    expect(CompetitionMatch::query()->count())->toBe(2);
});

test('deleting a match day keeps the times of its played matches', function () {
    $matchDay = MatchDay::factory()->create();
    $field = MatchDayField::factory()->create(['match_day_id' => $matchDay]);

    $played = CompetitionMatch::factory()->scheduled($matchDay, $field, '19:00')->played()->create();

    $matchDay->delete();

    $fresh = $played->fresh();

    expect($fresh->match_day_id)->toBeNull()
        ->and($fresh->match_day_field_id)->toBeNull()
        ->and($fresh->starts_at)->toBe('19:00')
        ->and($fresh->ends_at)->toBe('19:20');
});

test('deleting a field clears the schedule of pending matches on that field', function () {
    $matchDay = MatchDay::factory()->create();
    $field = MatchDayField::factory()->create(['match_day_id' => $matchDay]);

    $pending = CompetitionMatch::factory()->scheduled($matchDay, $field, '19:00')->create();

    $field->delete();

    $fresh = $pending->fresh();

    expect($fresh->match_day_id)->toBeNull()
        ->and($fresh->match_day_field_id)->toBeNull()
        ->and($fresh->starts_at)->toBeNull()
        ->and($fresh->ends_at)->toBeNull()
        ->and($fresh->pinned_at)->toBeNull()
        ->and($fresh->scheduling_failure)->toBeNull();
});

test('a new match day is stamped with the moment availability is requested', function () {
    $this->freezeSecond();

    $matchDay = MatchDay::factory()->create();

    expect($matchDay->availability_requested_at->equalTo(now()))->toBeTrue();
});

test('changing only the times or saving the same date keeps the availability request', function () {
    $matchDay = MatchDay::factory()->create(['date' => '2026-10-02']);
    $requestedAt = $matchDay->availability_requested_at;

    $this->travel(1)->minutes();

    $matchDay->update(['starts_at' => '10:00']);
    $matchDay->update(['date' => '2026-10-02']);

    expect($matchDay->fresh()->availability_requested_at->equalTo($requestedAt))->toBeTrue();
});

test('moving a match day to another date renews the availability request', function () {
    $matchDay = MatchDay::factory()->create(['date' => '2026-10-02']);
    $requestedAt = $matchDay->availability_requested_at;

    $this->travel(1)->minutes();
    $matchDay->update(['date' => '2026-10-09']);

    expect($matchDay->fresh()->availability_requested_at->greaterThan($requestedAt))->toBeTrue()
        ->and($matchDay->fresh()->isNewSince($requestedAt))->toBeTrue();
});

test('moving a match day through the model drops its availability and releases its pending matches', function () {
    $matchDay = MatchDay::factory()->create(['date' => '2026-10-02']);
    $otherDay = MatchDay::factory()->create(['competition_id' => $matchDay->competition_id, 'date' => '2026-10-03']);
    $field = MatchDayField::factory()->create(['match_day_id' => $matchDay]);
    $pending = CompetitionMatch::factory()->scheduled($matchDay, $field, '09:00')->create();

    MatchDayAvailability::factory()->create(['match_day_id' => $matchDay]);
    MatchDayAvailability::factory()->create(['match_day_id' => $otherDay]);

    $matchDay->update(['date' => '2026-10-09']);

    expect(MatchDayAvailability::query()->pluck('match_day_id')->all())->toBe([$otherDay->id])
        ->and($pending->fresh()->match_day_id)->toBeNull()
        ->and($pending->fresh()->starts_at)->toBeNull();
});

test('changing only the times through the model keeps the availability and the schedule', function () {
    $matchDay = MatchDay::factory()->create(['date' => '2026-10-02']);
    $field = MatchDayField::factory()->create(['match_day_id' => $matchDay]);
    $pending = CompetitionMatch::factory()->scheduled($matchDay, $field, '09:00')->create();
    MatchDayAvailability::factory()->create(['match_day_id' => $matchDay]);

    $matchDay->update(['date' => '2026-10-02', 'starts_at' => '08:00', 'ends_at' => '18:00']);

    expect(MatchDayAvailability::query()->where('match_day_id', $matchDay->id)->count())->toBe(1)
        ->and($pending->fresh()->match_day_id)->toBe($matchDay->id);
});

test('a date read back without a time, as MySQL returns it, does not count as moved', function () {
    $matchDay = MatchDay::factory()->create(['date' => '2026-10-02']);
    MatchDayAvailability::factory()->create(['match_day_id' => $matchDay]);
    $requestedAt = $matchDay->availability_requested_at;

    // SQLite bewaart `2026-10-02 00:00:00`; MySQL geeft `2026-10-02` terug
    // en gaat daarmee door de normalisatie van `originalIsEquivalent()`.
    DB::table('match_days')->where('id', $matchDay->id)->update(['date' => '2026-10-02']);

    $this->travel(1)->minutes();

    $matchDay->fresh()->update(['date' => '2026-10-02', 'starts_at' => '10:00']);

    expect($matchDay->fresh()->availability_requested_at->equalTo($requestedAt))->toBeTrue()
        ->and(MatchDayAvailability::query()->where('match_day_id', $matchDay->id)->count())->toBe(1);
});
