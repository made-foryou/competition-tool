<?php

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\User;

beforeEach(function () {
    $this->competition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $this->competition]);

    $this->user = User::factory()->participant()->create();
    $this->competition->participants()->attach($this->user);
});

test('a participant without submitted availability is sent to the form', function () {
    $this->actingAs($this->user)
        ->get(route('competition.dashboard', $this->competition))
        ->assertRedirect(route('competition.availability.edit', $this->competition));
});

test('the settings pages are blocked as well', function () {
    $this->actingAs($this->user)
        ->get(route('competition.settings.index', $this->competition))
        ->assertRedirect(route('competition.availability.edit', $this->competition));
});

test('the form itself stays reachable', function () {
    $this->actingAs($this->user)
        ->get(route('competition.availability.edit', $this->competition))
        ->assertOk();
});

test('the dashboard is reachable once the availability is submitted', function () {
    $this->actingAs($this->user)
        ->put(route('competition.availability.update', $this->competition), []);

    $this->actingAs($this->user)
        ->get(route('competition.dashboard', $this->competition))
        ->assertOk();
});

test('a competition without match days does not block', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.dashboard', $competition))
        ->assertOk();
});

test('a finished competition does not block', function () {
    $competition = Competition::factory()->finished()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);

    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.dashboard', $competition))
        ->assertOk();
});

test('a draft competition stays hidden instead of redirecting to the form', function () {
    $competition = Competition::factory()->draft()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);

    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.dashboard', $competition))
        ->assertNotFound();
});

test('admins are never blocked', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $this->competition->participants()->attach($admin);

    $this->actingAs($admin)
        ->get(route('competitions.edit', $this->competition))
        ->assertOk();
});

test('guests are not affected', function () {
    $this->get(route('competition.login', $this->competition))->assertOk();
});

test('after submitting one competition the next open one follows', function () {
    $second = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $second]);
    $second->participants()->attach($this->user);

    $this->actingAs($this->user)
        ->put(route('competition.availability.update', $this->competition), []);

    $this->actingAs($this->user)
        ->get(route('competition.dashboard', $this->competition))
        ->assertRedirect(route('competition.availability.edit', $second));
});
