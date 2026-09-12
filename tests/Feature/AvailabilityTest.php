<?php

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->competition = Competition::factory()->create();
    $this->firstDay = MatchDay::factory()->create(['competition_id' => $this->competition, 'date' => '2026-10-02']);
    $this->secondDay = MatchDay::factory()->create(['competition_id' => $this->competition, 'date' => '2026-10-03']);

    $this->user = User::factory()->participant()->create();
    $this->competition->participants()->attach($this->user, ['availability_submitted_at' => now()]);

    $this->actingAs($this->user);
});

test('a participant sees the match days with their current availability', function () {
    MatchDayAvailability::factory()->create([
        'match_day_id' => $this->firstDay,
        'user_id' => $this->user,
    ]);

    $this->get(route('competition.availability.edit', $this->competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('participant/availability')
            ->has('matchDays', 2)
            ->where('matchDays.0.is_available', true)
            ->where('matchDays.1.is_available', false)
            ->where('hasSubmitted', true),
        );
});

test('a participant can save their availability', function () {
    $this->put(route('competition.availability.update', $this->competition), [
        'match_days' => [$this->firstDay->id, $this->secondDay->id],
    ])->assertRedirect();

    expect($this->user->matchDayAvailabilities()->count())->toBe(2);
});

test('saving again replaces the previous selection', function () {
    $this->put(route('competition.availability.update', $this->competition), [
        'match_days' => [$this->firstDay->id, $this->secondDay->id],
    ]);

    $this->put(route('competition.availability.update', $this->competition), [
        'match_days' => [$this->secondDay->id],
    ]);

    expect($this->user->matchDayAvailabilities()->pluck('match_day_id')->all())
        ->toBe([$this->secondDay->id]);
});

test('saving does not touch the availability of another competition', function () {
    $other = Competition::factory()->create();
    $otherDay = MatchDay::factory()->create(['competition_id' => $other]);
    $other->participants()->attach($this->user, ['availability_submitted_at' => now()]);

    MatchDayAvailability::factory()->create([
        'match_day_id' => $otherDay,
        'user_id' => $this->user,
    ]);

    $this->put(route('competition.availability.update', $this->competition), [
        'match_days' => [$this->firstDay->id],
    ]);

    expect($this->user->matchDayAvailabilities()->pluck('match_day_id')->sort()->values()->all())
        ->toBe(collect([$otherDay->id, $this->firstDay->id])->sort()->values()->all());
});

test('saving without any match day counts as submitted', function () {
    $user = User::factory()->participant()->create();
    $this->competition->participants()->attach($user);

    $this->actingAs($user)
        ->put(route('competition.availability.update', $this->competition), [])
        ->assertRedirect(route('competition.dashboard', $this->competition));

    expect($user->hasSubmittedAvailabilityFor($this->competition))->toBeTrue()
        ->and($user->matchDayAvailabilities()->count())->toBe(0);
});

test('a match day of another competition is rejected', function () {
    $otherDay = MatchDay::factory()->create();

    $this->put(route('competition.availability.update', $this->competition), [
        'match_days' => [$otherDay->id],
    ])->assertSessionHasErrors('match_days.0');
});

test('someone who does not participate cannot open the form', function () {
    $this->actingAs(User::factory()->participant()->create())
        ->get(route('competition.availability.edit', $this->competition))
        ->assertForbidden();
});

test('guests are redirected to the competition login page', function () {
    auth()->logout();

    $this->get(route('competition.availability.edit', $this->competition))
        ->assertRedirect(route('competition.login', $this->competition));
});
