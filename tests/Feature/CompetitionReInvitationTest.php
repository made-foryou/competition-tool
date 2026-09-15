<?php

use App\Actions\Auth\SendInvitation;
use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\Invitation;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Het seizoensscenario: iedereen heeft al een account en wordt opnieuw
 * uitgenodigd, zodat hij zelf beslist of hij meedoet.
 */
beforeEach(function () {
    $this->competition = Competition::factory()->create(['name' => 'Voorjaarstoernooi 2027']);
    $this->matchDay = MatchDay::factory()->create(['competition_id' => $this->competition->id]);

    // Een zittende deelnemer, zodat de wedstrijdensync iets te doen heeft.
    $this->existingParticipant = User::factory()->participant()->create();
    $this->competition->participants()->attach($this->existingParticipant);

    $this->formerParticipant = User::factory()->participant()->create([
        'email' => 'oud-deelnemer@example.com',
    ]);
});

test('an invited former participant logs in and joins the new competition', function () {
    $plainToken = app(SendInvitation::class)->handle(
        $this->formerParticipant->email,
        UserRole::Participant,
        $this->competition,
    );

    // 1. De uitnodigingslink vraagt om in te loggen.
    $this->get(route('invitation.show', $plainToken))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('invitationState', 'sign-in'));

    // 2. Inloggen brengt hem bij de aanmeldpagina van deze competitie.
    $this->post(route('login.store'), [
        'email' => $this->formerParticipant->email,
        'password' => 'password',
    ])->assertRedirect(route('competition.dashboard', $this->competition));

    $this->get(route('competition.dashboard', $this->competition))
        ->assertRedirect(route('competition.register.show', $this->competition));

    $this->get(route('competition.register.show', $this->competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/competition-register')
            ->where('registrationState', 'open')
            ->where('authenticated', true),
        );

    // 3. Aanmelden met zijn beschikbaarheid.
    $this->post(route('competition.register.store', $this->competition), [
        'match_days' => [$this->matchDay->id],
    ])->assertRedirect(route('competition.dashboard', $this->competition));

    expect($this->competition->participants()->whereKey($this->formerParticipant->id)->exists())->toBeTrue()
        ->and($this->formerParticipant->hasSubmittedAvailabilityFor($this->competition))->toBeTrue()
        ->and(MatchDayAvailability::query()
            ->where('user_id', $this->formerParticipant->id)
            ->where('match_day_id', $this->matchDay->id)
            ->exists())->toBeTrue()
        ->and(CompetitionMatch::query()->where('competition_id', $this->competition->id)->count())->toBe(1)
        // De uitnodiging is beantwoord en verdwijnt uit de openstaande lijst.
        ->and($this->competition->invitations()->pending()->count())->toBe(0);
});

test('a forwarded registration link also settles the pending invitation', function () {
    app(SendInvitation::class)->handle(
        $this->formerParticipant->email,
        UserRole::Participant,
        $this->competition,
    );

    // Hij gebruikt de doorgestuurde inschrijflink en raakt de uitnodigingsmail
    // nooit aan; de beheerder hoort hem toch niet meer te hoeven rappelleren.
    $this->actingAs($this->formerParticipant)
        ->post(route('competition.register.store', $this->competition), [
            'match_days' => [$this->matchDay->id],
        ])->assertRedirect(route('competition.dashboard', $this->competition));

    expect($this->competition->participants()->whereKey($this->formerParticipant->id)->exists())->toBeTrue()
        ->and($this->competition->invitations()->pending()->count())->toBe(0)
        ->and(Invitation::query()
            ->where('email', $this->formerParticipant->email)
            ->whereNotNull('accepted_at')
            ->exists())->toBeTrue();
});

test('an invitation for another competition is left alone when signing up', function () {
    $otherCompetition = Competition::factory()->create();

    app(SendInvitation::class)->handle(
        $this->formerParticipant->email,
        UserRole::Participant,
        $otherCompetition,
    );

    $this->actingAs($this->formerParticipant)
        ->post(route('competition.register.store', $this->competition), [
            'match_days' => [],
        ])->assertRedirect(route('competition.dashboard', $this->competition));

    expect($otherCompetition->invitations()->pending()->count())->toBe(1);
});

test('a declined invitee can be invited again', function () {
    $plainToken = app(SendInvitation::class)->handle(
        $this->formerParticipant->email,
        UserRole::Participant,
        $this->competition,
    );

    $this->post(route('invitation.decline', $plainToken));

    expect($this->competition->invitations()->pending()->count())->toBe(0);

    $newToken = app(SendInvitation::class)->handle(
        $this->formerParticipant->email,
        UserRole::Participant,
        $this->competition,
    );

    $this->get(route('invitation.show', $newToken))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('invitationState', 'sign-in'));

    expect($this->competition->invitations()->pending()->count())->toBe(1);
});
