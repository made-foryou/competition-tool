<?php

use App\Models\Competition;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a linked participant can view the competition dashboard', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.dashboard', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('participant/dashboard')
            ->where('competition.name', $competition->name)
            ->has('participants', 1),
        );
});

test('an unlinked participant is sent to the registration page', function () {
    $competition = Competition::factory()->create();

    $this->actingAs(User::factory()->participant()->create())
        ->get(route('competition.dashboard', $competition))
        ->assertRedirect(route('competition.register.show', $competition));
});

test('admins can view any competition dashboard', function () {
    $competition = Competition::factory()->create();

    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(route('competition.dashboard', $competition))
        ->assertOk();
});

test('guests are redirected to the competition login page', function () {
    $competition = Competition::factory()->create();

    $this->get(route('competition.dashboard', $competition))
        ->assertRedirect(route('competition.login', $competition));
});

test('the competition login page renders for guests', function () {
    $competition = Competition::factory()->create();

    $this->get(route('competition.login', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/competition-login')
            ->where('competitionName', $competition->name),
        );
});

test('an authenticated participant visiting the login page is sent to the dashboard', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.login', $competition))
        ->assertRedirect(route('competition.dashboard', $competition));
});

test('draft competitions are hidden from participants and guests', function () {
    $competition = Competition::factory()->draft()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->get(route('competition.login', $competition))->assertNotFound();
    $this->actingAs($user)
        ->get(route('competition.dashboard', $competition))
        ->assertNotFound();
});

test('draft competitions remain visible for admins', function () {
    $competition = Competition::factory()->draft()->create();

    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(route('competition.dashboard', $competition))
        ->assertOk();
});

test('finished competitions remain accessible for participants', function () {
    $competition = Competition::factory()->finished()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.dashboard', $competition))
        ->assertOk();
});

test('an unknown slug returns a 404', function () {
    $this->get('/bestaat-niet')->assertNotFound();
});

test('visiting the competition login page as a guest sets the dashboard as the intended url', function () {
    $competition = Competition::factory()->create();

    $this->get(route('competition.login', $competition))->assertOk();

    $this->assertEquals(route('competition.dashboard', $competition), session('url.intended'));
});

test('participants only see id and name of other participants', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.dashboard', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->has('participants.0', fn (Assert $participant) => $participant
                ->has('id')
                ->has('name'),
            ),
        );
});

test('a non-get request from an unlinked participant gets a 303', function () {
    $competition = Competition::factory()->create();

    $this->actingAs(User::factory()->participant()->create())
        ->put(route('competition.availability.update', $competition), ['match_days' => []])
        ->assertStatus(303)
        ->assertRedirect(route('competition.register.show', $competition));
});

test('a draft competition stays hidden for an unlinked participant', function () {
    $competition = Competition::factory()->draft()->create();

    $this->actingAs(User::factory()->participant()->create())
        ->get(route('competition.dashboard', $competition))
        ->assertNotFound();
});

test('a finished competition sends an unlinked participant to the closed registration page', function () {
    $competition = Competition::factory()->finished()->create();

    $this->actingAs(User::factory()->participant()->create())
        ->get(route('competition.dashboard', $competition))
        ->assertRedirect(route('competition.register.show', $competition));
});
