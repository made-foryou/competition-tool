<?php

use App\Models\Competition;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an unknown url renders the error page', function () {
    $this->get('/bestaat-niet')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error')
            ->where('status', 404),
        );
});

test('the error page carries the shared translations', function () {
    // Bewaakt de withSharedData()-aanroep in AppServiceProvider: zonder die
    // props valt useTranslations terug op een ontbrekende translations-map en
    // crasht de pagina in de browser, zonder dat de statuscode iets verraadt.
    $this->get('/bestaat-niet')
        ->assertInertia(fn (Assert $page) => $page->has('translations'));
});

test('a guest is sent back to the home page', function () {
    $this->get('/bestaat-niet')
        ->assertInertia(fn (Assert $page) => $page
            ->where('returnUrl', route('home'))
            ->where('returnLabel', 'Terug naar de startpagina'),
        );
});

test('a draft competition renders the error page for a guest', function () {
    $draft = Competition::factory()->draft()->create();

    $this->get(route('competition.register.show', $draft))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error')
            ->where('status', 404),
        );
});

test('a participant on an admin route gets a forbidden error page', function () {
    $this->actingAs(User::factory()->participant()->create())
        ->get(route('dashboard'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error')
            ->where('status', 403),
        );
});

test('an admin is sent back to the admin dashboard', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get('/bestaat-niet')
        ->assertInertia(fn (Assert $page) => $page
            ->where('returnUrl', route('dashboard'))
            ->where('returnLabel', 'Naar je competities'),
        );
});

test('a participant without an active competition is sent to the no competition page', function () {
    $this->actingAs(User::factory()->participant()->create())
        ->get('/bestaat-niet')
        ->assertInertia(fn (Assert $page) => $page
            ->where('returnUrl', route('competition.none')),
        );
});

test('a participant is sent back to their active competition', function () {
    $competition = Competition::factory()->create();
    $participant = User::factory()->participant()->create();
    $competition->participants()->attach($participant);

    $this->actingAs($participant)
        ->get('/bestaat-niet')
        ->assertInertia(fn (Assert $page) => $page
            ->where('returnUrl', route('competition.dashboard', $competition)),
        );
});
