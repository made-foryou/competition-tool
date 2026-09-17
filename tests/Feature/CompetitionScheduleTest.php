<?php

use App\Actions\Competitions\ScheduleCompetitionMatches;
use App\Actions\Competitions\SyncCompetitionMatches;
use App\Enums\MatchStatus;
use App\Enums\SchedulingBlocker;
use App\Enums\SchedulingFailure;
use App\Enums\SchedulingMode;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\MatchDayField;
use App\Models\User;
use App\Support\CompetitionSettings;
use App\Support\Scheduling\ClockTime;
use App\Support\Scheduling\SlotGrid;
use Illuminate\Support\Facades\DB;

/**
 * Een actieve competitie met deelnemers, de bijbehorende round-robin, een
 * aantal speeldagen met tafels en voor iedere deelnemer beschikbaarheid op
 * iedere speeldag.
 *
 * @param  array<string, mixed>  $settings
 */
function scheduleFixture(int $players, int $days, int $fields, array $settings = [], string $startsAt = '19:00', string $endsAt = '23:00'): Competition
{
    $competition = Competition::factory()
        ->withSettings(CompetitionSettings::fromArray($settings))
        ->create();

    $participants = User::factory()->participant()->count($players)->create();
    $competition->participants()->attach($participants->modelKeys());

    app(SyncCompetitionMatches::class)->handle($competition);

    for ($index = 0; $index < $days; $index++) {
        $matchDay = MatchDay::factory()
            ->withFields($fields)
            ->create([
                'competition_id' => $competition->id,
                'date' => now()->addWeek()->addDays($index)->toDateString(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

        foreach ($participants as $participant) {
            MatchDayAvailability::factory()->create([
                'match_day_id' => $matchDay->id,
                'user_id' => $participant->id,
            ]);
        }
    }

    return $competition->refresh();
}

/**
 * Zet een bestaande wedstrijd op een vaste plek.
 *
 * Gaat via de query builder omdat de planningskolommen bewust niet fillable
 * zijn, en op een bestaande wedstrijd omdat de unique-index per spelerspaar
 * maar één wedstrijd per paar toelaat — een tweede, via de factory
 * aangemaakte wedstrijd zou daar tegenaan lopen.
 *
 * @param  array<string, mixed>  $attributes  extra kolommen, bijvoorbeeld de status of `pinned_at`
 */
function placeMatchAt(CompetitionMatch $match, MatchDay $matchDay, ?MatchDayField $field, string $startsAt, array $attributes = []): void
{
    $duration = $match->competition->settings->matchDurationMinutes;

    CompetitionMatch::query()
        ->whereKey($match->id)
        ->update(array_merge([
            'match_day_id' => $matchDay->id,
            'match_day_field_id' => $field?->id,
            'starts_at' => $startsAt.':00',
            'ends_at' => ClockTime::fromMinutes(ClockTime::toMinutes($startsAt) + $duration).':00',
            'scheduling_failure' => null,
        ], $attributes));
}

/**
 * Bewaakt de harde randvoorwaarden op alle wedstrijden van een competitie die
 * een speeldag én een begintijd hebben: geen speler of tafel dubbel bezet,
 * beide spelers beschikbaar, binnen het raster en de openingstijden, nooit in
 * de pauze en nooit meer dan het dagmaximum.
 *
 * Gespeelde en vastgezette wedstrijden blijven staan waar ze staan, ook als de
 * instellingen of de openingstijden daarna gewijzigd zijn; voor hen vervalt
 * alleen de eis dat ze op het huidige raster liggen. Ze tellen wél mee voor de
 * spelersbezetting en het dagmaximum, ook zonder tafel.
 */
function expectValidSchedule(Competition $competition): void
{
    $settings = $competition->settings;
    $matchDays = $competition->matchDays()->get()->keyBy('id');

    // Alleen de huidige deelnemers: beschikbaarheid van een oud-deelnemer
    // hoort geen enkele plaatsing goed te praten.
    $availability = MatchDayAvailability::query()
        ->whereIn('match_day_id', $matchDays->modelKeys())
        ->whereIn('user_id', $competition->participants()->pluck('users.id'))
        ->get()
        ->map(fn (MatchDayAvailability $row): string => $row->match_day_id.':'.$row->user_id)
        ->all();

    $playerIntervals = [];
    $tableIntervals = [];
    $matchesPerPlayerPerDay = [];

    foreach ($competition->matches()->get() as $match) {
        if ($match->match_day_id === null || $match->starts_at === null) {
            continue;
        }

        expect($match->ends_at)->not->toBeNull();

        $matchDay = $matchDays->get($match->match_day_id);
        $grid = SlotGrid::for($matchDay->starts_at, $matchDay->ends_at, $settings);

        $start = ClockTime::toMinutes($match->starts_at);
        $end = ClockTime::toMinutes($match->ends_at);

        if (! $match->isLocked()) {
            $slot = $grid->slotAt($match->starts_at);

            expect($slot)->not->toBeNull()
                ->and($end)->toBeLessThanOrEqual(ClockTime::toMinutes($matchDay->ends_at));

            if ($grid->break !== null) {
                expect($start < $grid->break['end_minute'] && $grid->break['start_minute'] < $end)->toBeFalse();
            }
        }

        expect($availability)->toContain($match->match_day_id.':'.$match->first_player_id)
            ->and($availability)->toContain($match->match_day_id.':'.$match->second_player_id);

        foreach ([$match->first_player_id, $match->second_player_id] as $playerId) {
            $key = $match->match_day_id.':'.$playerId;

            foreach ($playerIntervals[$key] ?? [] as [$busyStart, $busyEnd]) {
                expect($start < $busyEnd && $busyStart < $end)->toBeFalse();
            }

            $playerIntervals[$key][] = [$start, $end];
            $matchesPerPlayerPerDay[$key] = ($matchesPerPlayerPerDay[$key] ?? 0) + 1;
        }

        if ($match->match_day_field_id === null) {
            continue;
        }

        $tableKey = $match->match_day_id.':'.$match->match_day_field_id;
        $tableEnd = $end + $settings->bufferMinutes;

        foreach ($tableIntervals[$tableKey] ?? [] as [$busyStart, $busyEnd]) {
            expect($start < $busyEnd && $busyStart < $tableEnd)->toBeFalse();
        }

        $tableIntervals[$tableKey][] = [$start, $tableEnd];
    }

    if ($settings->maxMatchesPerPlayerPerDay > 0) {
        foreach ($matchesPerPlayerPerDay as $count) {
            expect($count)->toBeLessThanOrEqual($settings->maxMatchesPerPlayerPerDay);
        }
    }
}

/**
 * De planning als vergelijkbare vingerafdruk: per spelerspaar op volgnummer
 * de speeldag (volgnummer), de tafel (positie) en de begintijd. Ids
 * verschillen tussen twee fixtures, volgnummers en posities niet.
 *
 * @return array<string, string>
 */
function scheduleSignature(Competition $competition): array
{
    $players = array_flip($competition->participants()->orderBy('users.id')->pluck('users.id')->all());
    $matchDays = array_flip($competition->matchDays()->pluck('id')->all());
    $positions = MatchDayField::query()
        ->whereIn('match_day_id', array_keys($matchDays))
        ->pluck('position', 'id')
        ->all();

    $signature = [];

    foreach ($competition->matches()->get() as $match) {
        $pair = $players[$match->first_player_id].'-'.$players[$match->second_player_id];

        $signature[$pair] = $match->isScheduled()
            ? $matchDays[$match->match_day_id].':'.$positions[$match->match_day_field_id].':'.$match->starts_at
            : 'unscheduled';
    }

    ksort($signature);

    return $signature;
}

/**
 * De ruwe wedstrijdenrijen, om te bewijzen dat een geblokkeerde run niets
 * heeft aangeraakt.
 *
 * @return list<array<string, mixed>>
 */
function matchesSnapshot(): array
{
    return DB::table('matches')
        ->orderBy('id')
        ->get()
        ->map(fn (object $row): array => (array) $row)
        ->all();
}

/**
 * Een competitie die op precies één preconditie stukloopt.
 */
function blockedFixture(string $case): Competition
{
    $competition = match ($case) {
        'no_match_days' => scheduleFixture(3, 0, 2),
        default => scheduleFixture(3, 1, 2),
    };

    match ($case) {
        'no_fields' => MatchDayField::query()->delete(),
        'no_availability' => MatchDayAvailability::query()->delete(),
        'no_matches' => CompetitionMatch::query()->delete(),
        'nothing_to_schedule' => app(ScheduleCompetitionMatches::class)->handle($competition),
        default => null,
    };

    return $competition;
}

test('every match is scheduled when capacity suffices', function () {
    $competition = scheduleFixture(4, 2, 2);

    $result = app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($result->isBlocked())->toBeFalse()
        ->and($result->isComplete())->toBeTrue()
        ->and($result->scheduledCount)->toBe(6)
        ->and($result->keptCount)->toBe(0)
        ->and($result->unscheduledCount)->toBe(0)
        ->and($competition->matches()->whereNotNull('match_day_id')->whereNotNull('match_day_field_id')->whereNotNull('starts_at')->count())->toBe(6)
        ->and($competition->matches()->whereNotNull('scheduling_failure')->count())->toBe(0);

    expectValidSchedule($competition);
});

test('starts_at and ends_at are stored as H:i and ends_at equals starts_at plus the match duration', function () {
    $competition = scheduleFixture(4, 2, 2);

    app(ScheduleCompetitionMatches::class)->handle($competition);

    foreach ($competition->matches()->get() as $match) {
        expect($match->starts_at)->toMatch('/^\d{2}:\d{2}$/')
            ->and($match->ends_at)->toMatch('/^\d{2}:\d{2}$/')
            ->and(ClockTime::toMinutes($match->ends_at) - ClockTime::toMinutes($match->starts_at))
            ->toBe($competition->settings->matchDurationMinutes);
    }
});

test('a player is never scheduled on a match day they are not available on', function () {
    $competition = scheduleFixture(4, 2, 2);
    $firstDay = $competition->matchDays()->first();
    $participant = $competition->participants()->orderBy('users.id')->first();

    MatchDayAvailability::query()
        ->where('match_day_id', $firstDay->id)
        ->where('user_id', $participant->id)
        ->delete();

    app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($competition->matches()
        ->where('match_day_id', $firstDay->id)
        ->where(fn ($query) => $query
            ->where('first_player_id', $participant->id)
            ->orWhere('second_player_id', $participant->id))
        ->count())->toBe(0);

    expectValidSchedule($competition);
});

test('nothing is scheduled outside the opening hours or during the break', function () {
    $competition = scheduleFixture(6, 1, 1, [], '19:00', '20:30');

    app(ScheduleCompetitionMatches::class)->handle($competition);

    $startTimes = $competition->matches()->whereNotNull('starts_at')->pluck('starts_at')->all();

    expect($startTimes)->not->toBeEmpty();

    foreach ($startTimes as $startsAt) {
        expect(substr($startsAt, 0, 5))->toBeIn(['19:00', '19:25', '20:05']);
    }

    expectValidSchedule($competition);
});

test('the maximum number of matches per player per day is never exceeded', function () {
    $competition = scheduleFixture(4, 1, 2, ['max_matches_per_player_per_day' => 2]);

    $result = app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($result->scheduledCount + $result->unscheduledCount)->toBe(6)
        ->and($result->unscheduledCount)->toBeGreaterThan(0)
        ->and($result->isComplete())->toBeFalse();

    foreach ($result->failures as $reason) {
        expect($reason)->toBe(SchedulingFailure::MaxMatchesPerDayReached);
    }

    expectValidSchedule($competition);
});

test('a played match keeps its field and time and blocks that slot', function () {
    $competition = scheduleFixture(4, 1, 2);
    $matchDay = $competition->matchDays()->first();
    $field = $matchDay->fields()->first();
    $played = $competition->matches()->first();

    placeMatchAt($played, $matchDay, $field, '19:00', ['status' => MatchStatus::Played->value]);

    app(ScheduleCompetitionMatches::class)->handle($competition);

    $played->refresh();

    expect($played->match_day_id)->toBe($matchDay->id)
        ->and($played->match_day_field_id)->toBe($field->id)
        ->and($played->starts_at)->toBe('19:00')
        ->and($competition->matches()
            ->whereKeyNot($played->id)
            ->where('match_day_field_id', $field->id)
            ->where('starts_at', '19:00:00')
            ->count())->toBe(0);

    expectValidSchedule($competition);
});

test('a played match counts towards the daily maximum', function () {
    $competition = scheduleFixture(4, 1, 2, ['max_matches_per_player_per_day' => 1]);
    $matchDay = $competition->matchDays()->first();
    $field = $matchDay->fields()->first();
    $played = $competition->matches()->first();

    placeMatchAt($played, $matchDay, $field, '19:00', ['status' => MatchStatus::Played->value]);

    $result = app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($result->keptCount)->toBe(1)
        ->and($competition->matches()
            ->whereKeyNot($played->id)
            ->where(fn ($query) => $query
                ->where('first_player_id', $played->first_player_id)
                ->orWhere('second_player_id', $played->first_player_id))
            ->whereNotNull('starts_at')
            ->count())->toBe(0);

    expectValidSchedule($competition);
});

test('a match is still scheduled with too little rest when nothing else fits and is reported', function () {
    $competition = scheduleFixture(3, 1, 1, [
        'min_rest_minutes' => 60,
        'break_duration_minutes' => 0,
    ], '19:00', '20:20');

    $result = app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($result->scheduledCount)->toBe(3)
        ->and($result->restViolations)->not->toBeEmpty()
        ->and($competition->matches()->whereNull('starts_at')->count())->toBe(0);

    foreach ($result->restViolations as $matchId) {
        expect(CompetitionMatch::query()->findOrFail($matchId)->isScheduled())->toBeTrue();
    }

    expectValidSchedule($competition);
});

test('a pair without a shared match day is reported and does not stop the rest', function () {
    $competition = scheduleFixture(4, 2, 2);
    [$firstDay, $secondDay] = $competition->matchDays()->get()->all();
    [$first, $second] = $competition->participants()->orderBy('users.id')->get()->all();

    MatchDayAvailability::query()->where('match_day_id', $secondDay->id)->where('user_id', $first->id)->delete();
    MatchDayAvailability::query()->where('match_day_id', $firstDay->id)->where('user_id', $second->id)->delete();

    $result = app(ScheduleCompetitionMatches::class)->handle($competition);

    $pair = $competition->matches()
        ->where('first_player_id', $first->id)
        ->where('second_player_id', $second->id)
        ->firstOrFail();

    expect($pair->scheduling_failure)->toBe(SchedulingFailure::NoSharedMatchDay)
        ->and($pair->isScheduled())->toBeFalse()
        ->and($result->unscheduledCount)->toBe(1)
        ->and($result->scheduledCount)->toBe(5);

    expectValidSchedule($competition);
});

test('a scheduling failure is cleared once the match is scheduled', function () {
    $competition = scheduleFixture(4, 2, 2);
    $matchDay = $competition->matchDays()->first();
    $participant = $competition->participants()->orderBy('users.id')->first();

    MatchDayAvailability::query()->where('user_id', $participant->id)->delete();

    app(ScheduleCompetitionMatches::class)->handle($competition);

    $blocked = $competition->matches()
        ->where(fn ($query) => $query
            ->where('first_player_id', $participant->id)
            ->orWhere('second_player_id', $participant->id))
        ->get();

    expect($blocked)->toHaveCount(3);

    foreach ($blocked as $match) {
        expect($match->scheduling_failure)->toBe(SchedulingFailure::NoSharedMatchDay);
    }

    MatchDayAvailability::factory()->create([
        'match_day_id' => $matchDay->id,
        'user_id' => $participant->id,
    ]);

    app(ScheduleCompetitionMatches::class)->handle($competition);

    foreach ($blocked as $match) {
        $match->refresh();

        expect($match->scheduling_failure)->toBeNull()
            ->and($match->isScheduled())->toBeTrue();
    }

    expectValidSchedule($competition);
});

test('fill leaves already scheduled matches untouched and only places unscheduled ones', function () {
    $competition = scheduleFixture(4, 2, 2);

    app(ScheduleCompetitionMatches::class)->handle($competition);

    $untouched = $competition->matches()->get()->keyBy('id');
    $released = $competition->matches()->first();

    CompetitionMatch::query()->whereKey($released->id)->update([
        'match_day_id' => null,
        'match_day_field_id' => null,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    $result = app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($result->scheduledCount)->toBe(1)
        ->and($result->keptCount)->toBe(5)
        ->and($result->unscheduledCount)->toBe(0)
        ->and($released->refresh()->isScheduled())->toBeTrue();

    foreach ($competition->matches()->whereKeyNot($released->id)->get() as $match) {
        expect($match->match_day_id)->toBe($untouched[$match->id]->match_day_id)
            ->and($match->match_day_field_id)->toBe($untouched[$match->id]->match_day_field_id)
            ->and($match->starts_at)->toBe($untouched[$match->id]->starts_at);
    }

    expectValidSchedule($competition);
});

test('reschedule clears pending unpinned matches and keeps played and pinned ones in place', function () {
    $competition = scheduleFixture(4, 2, 2);

    app(ScheduleCompetitionMatches::class)->handle($competition);

    [$played, $pinned] = $competition->matches()->get()->take(2)->all();

    CompetitionMatch::query()->whereKey($played->id)->update(['status' => MatchStatus::Played->value]);
    CompetitionMatch::query()->whereKey($pinned->id)->update(['pinned_at' => now()]);

    $played->refresh();
    $pinned->refresh();

    $result = app(ScheduleCompetitionMatches::class)->handle($competition, SchedulingMode::Reschedule);

    expect($result->isComplete())->toBeTrue()
        ->and($result->scheduledCount)->toBe(4)
        ->and($result->keptCount)->toBe(2);

    foreach ([$played, $pinned] as $locked) {
        $fresh = CompetitionMatch::query()->findOrFail($locked->id);

        expect($fresh->match_day_id)->toBe($locked->match_day_id)
            ->and($fresh->match_day_field_id)->toBe($locked->match_day_field_id)
            ->and($fresh->starts_at)->toBe($locked->starts_at);
    }

    expectValidSchedule($competition);
});

test('reschedule may move a previously scheduled unpinned match', function () {
    $competition = scheduleFixture(3, 1, 1);
    $matchDay = $competition->matchDays()->first();
    $field = $matchDay->fields()->first();
    $match = $competition->matches()->first();

    placeMatchAt($match, $matchDay, $field, '22:35');

    app(ScheduleCompetitionMatches::class)->handle($competition, SchedulingMode::Reschedule);

    expect($match->refresh()->starts_at)->toBe('19:00');

    expectValidSchedule($competition);
});

test('a pending match with a deleted field is treated as unscheduled by fill', function () {
    $competition = scheduleFixture(4, 1, 2);

    app(ScheduleCompetitionMatches::class)->handle($competition);

    $orphaned = $competition->matches()->first();

    CompetitionMatch::query()->whereKey($orphaned->id)->update(['match_day_field_id' => null]);

    $result = app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($result->scheduledCount)->toBe(1)
        ->and($result->keptCount)->toBe(5)
        ->and($orphaned->refresh()->isScheduled())->toBeTrue();

    expectValidSchedule($competition);
});

test('running the planner twice on the same input yields identical placements', function () {
    $first = scheduleFixture(4, 2, 2);
    $second = scheduleFixture(4, 2, 2);

    app(ScheduleCompetitionMatches::class)->handle($first);
    app(ScheduleCompetitionMatches::class)->handle($second);

    expect(scheduleSignature($second))->toBe(scheduleSignature($first));
});

test('the planner result does not depend on the insertion order of fields', function () {
    $first = scheduleFixture(4, 2, 2);
    $second = scheduleFixture(4, 2, 2);

    foreach ($second->matchDays()->get() as $matchDay) {
        MatchDayField::query()->where('match_day_id', $matchDay->id)->delete();

        $matchDay->fields()->createMany([
            ['name' => 'Tafel 2', 'position' => 2],
            ['name' => 'Tafel 1', 'position' => 1],
        ]);
    }

    app(ScheduleCompetitionMatches::class)->handle($first);
    app(ScheduleCompetitionMatches::class)->handle($second);

    expect(scheduleSignature($second))->toBe(scheduleSignature($first));
});

test('the planner is blocked', function (string $case, SchedulingBlocker $blocker) {
    $competition = blockedFixture($case);
    $before = matchesSnapshot();

    $result = app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($result->isBlocked())->toBeTrue()
        ->and($result->blocker)->toBe($blocker)
        ->and($result->isComplete())->toBeFalse()
        ->and($result->scheduledCount)->toBe(0)
        ->and($result->keptCount)->toBe(0)
        ->and($result->unscheduledCount)->toBe(0)
        ->and($result->failures)->toBe([])
        ->and(matchesSnapshot())->toBe($before);
})->with([
    'without match days' => ['no_match_days', SchedulingBlocker::NoMatchDays],
    'without fields' => ['no_fields', SchedulingBlocker::NoFields],
    'when nobody is available' => ['no_availability', SchedulingBlocker::NoAvailability],
    'without pending matches' => ['no_matches', SchedulingBlocker::NoMatches],
    'when everything is already scheduled by fill' => ['nothing_to_schedule', SchedulingBlocker::NothingToSchedule],
]);

test('the blocker follows the fixed priority order', function () {
    $competition = scheduleFixture(3, 0, 2);
    CompetitionMatch::query()->delete();

    $result = app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($result->blocker)->toBe(SchedulingBlocker::NoMatchDays);
});

test('the result counts scheduled kept and unscheduled matches', function () {
    $competition = scheduleFixture(4, 2, 2);
    [$firstDay, $secondDay] = $competition->matchDays()->get()->all();
    [$first, $second, $third, $fourth] = $competition->participants()->orderBy('users.id')->get()->all();

    MatchDayAvailability::query()->where('match_day_id', $secondDay->id)->where('user_id', $first->id)->delete();
    MatchDayAvailability::query()->where('match_day_id', $firstDay->id)->where('user_id', $second->id)->delete();

    $kept = $competition->matches()
        ->where('first_player_id', $third->id)
        ->where('second_player_id', $fourth->id)
        ->firstOrFail();

    placeMatchAt($kept, $firstDay, $firstDay->fields()->first(), '19:00');

    $result = app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($result->keptCount)->toBe(1)
        ->and($result->scheduledCount)->toBe(4)
        ->and($result->unscheduledCount)->toBe(1)
        ->and($result->isComplete())->toBeFalse()
        ->and($result->failures)->toHaveCount(1)
        ->and($result->scheduledCount + $result->keptCount + $result->unscheduledCount)
        ->toBe($competition->matches()->count());

    expectValidSchedule($competition);
});

test('a played match without a field still blocks its players', function () {
    $competition = scheduleFixture(4, 1, 1);
    $matchDay = $competition->matchDays()->first();
    $played = $competition->matches()->first();

    placeMatchAt($played, $matchDay, null, '19:00', ['status' => MatchStatus::Played->value]);

    app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($competition->matches()
        ->whereKeyNot($played->id)
        ->where('starts_at', '19:00:00')
        ->where(fn ($query) => $query
            ->whereIn('first_player_id', [$played->first_player_id, $played->second_player_id])
            ->orWhereIn('second_player_id', [$played->first_player_id, $played->second_player_id]))
        ->count())->toBe(0)
        ->and($competition->matches()->whereKeyNot($played->id)->where('starts_at', '19:00:00')->count())->toBe(1);

    expectValidSchedule($competition);
});

test('a played match without a field counts as kept', function () {
    $competition = scheduleFixture(4, 1, 2);
    $matchDay = $competition->matchDays()->first();
    $played = $competition->matches()->first();

    placeMatchAt($played, $matchDay, null, '19:00', ['status' => MatchStatus::Played->value]);

    $result = app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($result->keptCount)->toBe(1)
        ->and($result->scheduledCount + $result->keptCount + $result->unscheduledCount)
        ->toBe($competition->matches()->count());

    expectValidSchedule($competition);
});

test('the planner uses the settings as stored at the moment of scheduling', function () {
    $competition = scheduleFixture(3, 1, 1);

    Competition::query()
        ->whereKey($competition->id)
        ->update(['settings' => json_encode(CompetitionSettings::fromArray([
            'match_duration_minutes' => 30,
        ])->toArray())]);

    app(ScheduleCompetitionMatches::class)->handle($competition);

    $scheduled = $competition->matches()->whereNotNull('starts_at')->get();

    expect($scheduled)->not->toBeEmpty()
        ->and($competition->settings->matchDurationMinutes)->toBe(CompetitionSettings::DEFAULT_MATCH_DURATION_MINUTES);

    foreach ($scheduled as $match) {
        expect(ClockTime::toMinutes($match->ends_at) - ClockTime::toMinutes($match->starts_at))->toBe(30);
    }
});

test('a stale row with only a field and a start time still blocks that table slot', function () {
    $competition = scheduleFixture(4, 1, 2);
    $matchDay = $competition->matchDays()->first();
    $field = $matchDay->fields()->first();
    $stale = $competition->matches()->first();

    placeMatchAt($stale, $matchDay, $field, '19:00', ['status' => MatchStatus::Played->value]);
    CompetitionMatch::query()->whereKey($stale->id)->update(['match_day_id' => null]);

    app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($competition->matches()
        ->whereKeyNot($stale->id)
        ->where('match_day_field_id', $field->id)
        ->where('starts_at', '19:00:00')
        ->count())->toBe(0)
        ->and($stale->refresh()->starts_at)->toBe('19:00');

    expectValidSchedule($competition);
});

test('the planner result does not depend on the insertion order of match days', function () {
    $first = scheduleFixture(4, 2, 2);
    $second = scheduleFixture(4, 2, 2);

    $participants = $second->participants()->orderBy('users.id')->get();
    $dates = $second->matchDays()->get()->map(fn (MatchDay $matchDay): string => $matchDay->date->toDateString())->all();

    $second->matchDays()->get()->each->delete();

    foreach (array_reverse($dates) as $date) {
        $matchDay = MatchDay::factory()
            ->withFields(2)
            ->create([
                'competition_id' => $second->id,
                'date' => $date,
                'starts_at' => '19:00',
                'ends_at' => '23:00',
            ]);

        foreach ($participants as $participant) {
            MatchDayAvailability::factory()->create([
                'match_day_id' => $matchDay->id,
                'user_id' => $participant->id,
            ]);
        }
    }

    app(ScheduleCompetitionMatches::class)->handle($first);
    app(ScheduleCompetitionMatches::class)->handle($second);

    expect(scheduleSignature($second))->toBe(scheduleSignature($first));
});

test('availability of a non-participant does not lift the no availability blocker', function () {
    $competition = scheduleFixture(3, 1, 2);
    $matchDay = $competition->matchDays()->first();

    MatchDayAvailability::query()->delete();

    MatchDayAvailability::factory()->create([
        'match_day_id' => $matchDay->id,
        'user_id' => User::factory()->participant()->create()->id,
    ]);

    $result = app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($result->isBlocked())->toBeTrue()
        ->and($result->blocker)->toBe(SchedulingBlocker::NoAvailability);
});

test('availability left behind by a former participant does not influence the schedule', function () {
    $competition = scheduleFixture(4, 2, 2);
    $former = $competition->participants()->orderBy('users.id')->first();

    $competition->participants()->detach($former->id);
    app(SyncCompetitionMatches::class)->handle($competition);

    expect(MatchDayAvailability::query()->where('user_id', $former->id)->count())->toBe(2);

    $result = app(ScheduleCompetitionMatches::class)->handle($competition);

    expect($result->isComplete())->toBeTrue()
        ->and($result->scheduledCount)->toBe(3)
        ->and($competition->matches()
            ->where(fn ($query) => $query
                ->where('first_player_id', $former->id)
                ->orWhere('second_player_id', $former->id))
            ->count())->toBe(0);

    expectValidSchedule($competition);
});
