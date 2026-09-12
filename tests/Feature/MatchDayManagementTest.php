<?php

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());

    $this->competition = Competition::factory()->create([
        'starts_at' => '2026-10-01',
        'ends_at' => '2026-10-10',
    ]);
});

test('admins can add a match day with automatically generated fields', function () {
    $editUrl = route('competitions.edit', $this->competition).'?tab=match-days';

    $this->from($editUrl)
        ->post(route('competitions.match-days.store', $this->competition), [
            'date' => '2026-10-02',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'field_count' => 3,
        ])->assertRedirect($editUrl)
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Match day added.')]);

    $matchDay = MatchDay::query()->firstOrFail();

    expect($matchDay->competition_id)->toBe($this->competition->id)
        ->and($matchDay->date->toDateString())->toBe('2026-10-02')
        ->and($matchDay->fields()->pluck('name')->all())->toBe(['Veld 1', 'Veld 2', 'Veld 3'])
        ->and($matchDay->fields()->pluck('position')->all())->toBe([1, 2, 3]);
});

test('the stored times are read back as H:i', function () {
    $this->post(route('competitions.match-days.store', $this->competition), [
        'date' => '2026-10-02',
        'starts_at' => '09:00',
        'ends_at' => '17:30',
        'field_count' => 1,
    ]);

    $matchDay = MatchDay::query()->firstOrFail();

    expect($matchDay->starts_at)->toBe('09:00')
        ->and($matchDay->ends_at)->toBe('17:30')
        ->and(DB::table('match_days')->value('starts_at'))->toBe('09:00:00');
});

test('the match day must fall within the competition period', function (string $date) {
    $this->post(route('competitions.match-days.store', $this->competition), [
        'date' => $date,
        'starts_at' => '09:00',
        'ends_at' => '17:00',
        'field_count' => 2,
    ])->assertSessionHasErrors('date');
})->with(['2026-09-30', '2026-10-11']);

test('the end time must be after the start time', function () {
    $this->post(route('competitions.match-days.store', $this->competition), [
        'date' => '2026-10-02',
        'starts_at' => '17:00',
        'ends_at' => '09:00',
        'field_count' => 2,
    ])->assertSessionHasErrors('ends_at');
});

test('the number of fields must be between one and the maximum', function (int $count) {
    $this->post(route('competitions.match-days.store', $this->competition), [
        'date' => '2026-10-02',
        'starts_at' => '09:00',
        'ends_at' => '17:00',
        'field_count' => $count,
    ])->assertSessionHasErrors('field_count');
})->with([0, MatchDay::MAX_FIELDS + 1]);

test('the edit page shows the match day and its fields', function () {
    $matchDay = MatchDay::factory()
        ->withFields(3)
        ->create(['competition_id' => $this->competition, 'date' => '2026-10-02']);

    $this->get(route('competitions.match-days.edit', [$this->competition, $matchDay]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('competitions/match-days/edit')
            ->has('fields', 3)
            ->where('matchDay.starts_at', '09:00')
            ->where('matchDay.date', '2026-10-02'),
        );
});

test('admins can update the date and times of a match day', function () {
    $matchDay = MatchDay::factory()->create(['competition_id' => $this->competition]);

    $this->put(route('competitions.match-days.update', [$this->competition, $matchDay]), [
        'date' => '2026-10-05',
        'starts_at' => '10:15',
        'ends_at' => '16:45',
    ])->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Match day updated.')]);

    $matchDay->refresh();

    expect($matchDay->date->toDateString())->toBe('2026-10-05')
        ->and($matchDay->starts_at)->toBe('10:15')
        ->and($matchDay->ends_at)->toBe('16:45');
});

test('a match day of another competition is not reachable', function () {
    $other = Competition::factory()->create();
    $matchDay = MatchDay::factory()->create(['competition_id' => $this->competition]);

    $this->get(route('competitions.match-days.edit', [$other, $matchDay]))->assertNotFound();
});

test('admins can delete a match day including its fields', function () {
    $matchDay = MatchDay::factory()
        ->withFields(2)
        ->create(['competition_id' => $this->competition]);

    $this->delete(route('competitions.match-days.destroy', [$this->competition, $matchDay]))
        ->assertRedirect(route('competitions.edit', $this->competition))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Match day removed.')]);

    expect(MatchDay::query()->count())->toBe(0)
        ->and(DB::table('match_day_fields')->count())->toBe(0);
});

test('the competition edit page lists the match days with their field count', function () {
    MatchDay::factory()->withFields(4)->create([
        'competition_id' => $this->competition,
        'date' => '2026-10-02',
    ]);
    MatchDay::factory()->create([
        'competition_id' => $this->competition,
        'date' => '2026-10-03',
    ]);

    $this->get(route('competitions.edit', $this->competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('competitions/edit')
            ->has('matchDays', 2)
            ->where('matchDays.0.date', '2026-10-02')
            ->where('matchDays.0.fields_count', 4)
            ->where('matchDays.1.fields_count', 0),
        );
});

test('participants cannot manage match days', function () {
    $matchDay = MatchDay::factory()->create(['competition_id' => $this->competition]);

    $this->actingAs(User::factory()->participant()->create());

    $this->get(route('competitions.match-days.edit', [$this->competition, $matchDay]))
        ->assertForbidden();

    $this->post(route('competitions.match-days.store', $this->competition), [])
        ->assertForbidden();
});

test('guests are redirected to the login page', function () {
    auth()->logout();

    $this->get(route('competitions.match-days.edit', [$this->competition, MatchDay::factory()->create(['competition_id' => $this->competition])]))
        ->assertRedirect(route('login'));
});
