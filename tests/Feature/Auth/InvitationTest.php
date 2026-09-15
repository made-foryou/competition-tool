<?php

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\Invitation;
use App\Models\MatchDay;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createInvitation(array $attributes = [], ?string $state = null): array
{
    $plainToken = Str::random(64);

    $factory = Invitation::factory();

    if ($state !== null) {
        $factory = $factory->{$state}();
    }

    $invitation = $factory->create([
        'token' => hash('sha256', $plainToken),
        ...$attributes,
    ]);

    return [$invitation, $plainToken];
}

test('the accept invitation page renders for a valid token', function () {
    [$invitation, $plainToken] = createInvitation();

    $this->get(route('invitation.show', $plainToken))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/accept-invitation')
            ->where('invitationState', 'open')
            ->where('email', $invitation->email)
            ->where('token', $plainToken),
        );
});

test('an unknown token returns a 404', function () {
    $this->get(route('invitation.show', Str::random(64)))
        ->assertNotFound();
});

test('an expired invitation shows the expired state', function () {
    [, $plainToken] = createInvitation(state: 'expired');

    $this->get(route('invitation.show', $plainToken))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/accept-invitation')
            ->where('invitationState', 'expired')
            ->missing('email'),
        );
});

test('an accepted invitation shows the expired state', function () {
    [, $plainToken] = createInvitation(state: 'accepted');

    $this->get(route('invitation.show', $plainToken))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/accept-invitation')
            ->where('invitationState', 'expired'),
        );
});

test('an invitation can be accepted', function () {
    [$invitation, $plainToken] = createInvitation();

    $response = $this->post(route('invitation.store', $plainToken), [
        'name' => 'Sanne de Vries',
        'password' => 'nieuw-wachtwoord',
        'password_confirmation' => 'nieuw-wachtwoord',
    ]);

    $response->assertRedirect(route('two-factor.setup'));

    $user = User::query()->where('email', $invitation->email)->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Sanne de Vries')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

test('an expired invitation cannot be accepted', function () {
    [, $plainToken] = createInvitation(state: 'expired');

    $this->post(route('invitation.store', $plainToken), [
        'name' => 'Sanne de Vries',
        'password' => 'nieuw-wachtwoord',
        'password_confirmation' => 'nieuw-wachtwoord',
    ])->assertRedirect(route('invitation.show', $plainToken));

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

test('an invitation cannot be accepted when the email already belongs to a user', function () {
    [$invitation, $plainToken] = createInvitation();

    User::factory()->create(['email' => $invitation->email]);

    $this->post(route('invitation.store', $plainToken), [
        'name' => 'Sanne de Vries',
        'password' => 'nieuw-wachtwoord',
        'password_confirmation' => 'nieuw-wachtwoord',
    ])->assertRedirect(route('invitation.show', $plainToken));

    $this->assertGuest();
});

test('accepting an invitation validates name and password', function () {
    [, $plainToken] = createInvitation();

    $this->post(route('invitation.store', $plainToken), [
        'name' => '',
        'password' => 'kort',
        'password_confirmation' => 'anders',
    ])->assertSessionHasErrors(['name', 'password']);

    $this->assertGuest();
});

test('accepting a participant invitation creates a participant linked to the competition', function () {
    $competition = Competition::factory()->create();
    $plainToken = Str::random(64);
    $invitation = Invitation::factory()->create([
        'token' => hash('sha256', $plainToken),
        'competition_id' => $competition->id,
        'role' => UserRole::Participant,
    ]);

    $response = $this->post(route('invitation.store', $plainToken), [
        'name' => 'Nieuwe Deelnemer',
        'password' => 'nieuw-wachtwoord',
        'password_confirmation' => 'nieuw-wachtwoord',
    ]);

    $user = User::query()->where('email', $invitation->email)->firstOrFail();

    expect($user->role)->toBe(UserRole::Participant)
        ->and($user->competitions()->whereKey($competition->id)->exists())->toBeTrue();

    $response->assertRedirect(route('competition.dashboard', $competition));
});

test('accepting a participant invitation without a competition redirects to no-competition', function () {
    $plainToken = Str::random(64);
    $invitation = Invitation::factory()->create([
        'token' => hash('sha256', $plainToken),
        'competition_id' => null,
        'role' => UserRole::Participant,
    ]);

    $response = $this->post(route('invitation.store', $plainToken), [
        'name' => 'Nieuwe Deelnemer',
        'password' => 'nieuw-wachtwoord',
        'password_confirmation' => 'nieuw-wachtwoord',
    ]);

    $response->assertRedirect(route('competition.none'));

    expect(User::query()->where('email', $invitation->email)->firstOrFail()->role)
        ->toBe(UserRole::Participant);
});

test('an invitation for a draft competition shows the upcoming state', function () {
    $draft = Competition::factory()->draft()->create();

    [, $plainToken] = createInvitation([
        'competition_id' => $draft->id,
        'role' => UserRole::Participant,
    ]);

    $this->get(route('invitation.show', $plainToken))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/accept-invitation')
            ->where('invitationState', 'upcoming')
            ->missing('email')
            ->missing('passwordRules'),
        );
});

test('accepting an admin invitation still redirects to two factor setup', function () {
    $plainToken = Str::random(64);
    $invitation = Invitation::factory()->create([
        'token' => hash('sha256', $plainToken),
    ]);

    $this->post(route('invitation.store', $plainToken), [
        'name' => 'Nieuwe Beheerder',
        'password' => 'nieuw-wachtwoord',
        'password_confirmation' => 'nieuw-wachtwoord',
    ])->assertRedirect(route('two-factor.setup'));

    expect(User::query()->where('email', $invitation->email)->firstOrFail()->role)
        ->toBe(UserRole::Admin);
});

test('accepting an invitation is rate limited', function () {
    [, $plainToken] = createInvitation();

    foreach (range(1, 6) as $attempt) {
        $this->post(route('invitation.store', $plainToken), [
            'name' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);
    }

    $this->post(route('invitation.store', $plainToken), [
        'name' => 'Sanne de Vries',
        'password' => 'nieuw-wachtwoord',
        'password_confirmation' => 'nieuw-wachtwoord',
    ])->assertTooManyRequests();
});

test('an invitation for a finished competition shows the closed state', function () {
    $finished = Competition::factory()->finished()->create();

    [, $plainToken] = createInvitation([
        'competition_id' => $finished->id,
        'role' => UserRole::Participant,
    ]);

    $this->get(route('invitation.show', $plainToken))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/accept-invitation')
            ->where('invitationState', 'closed')
            ->missing('email')
            ->missing('passwordRules'),
        );
});

test('an invitation for a finished competition cannot be accepted', function () {
    $finished = Competition::factory()->finished()->create();

    // Een bestaande deelnemer, zodat een geslaagde acceptatie een tweede
    // speler zou opleveren en de sync dus wel degelijk een wedstrijd zou
    // schrijven -- anders bewijst de assertie hieronder niets.
    $finished->participants()->attach(User::factory()->participant()->create());

    [$invitation, $plainToken] = createInvitation([
        'competition_id' => $finished->id,
        'role' => UserRole::Participant,
    ]);

    $this->post(route('invitation.store', $plainToken), [
        'name' => 'Nieuwe Deelnemer',
        'password' => 'nieuw-wachtwoord',
        'password_confirmation' => 'nieuw-wachtwoord',
    ])->assertRedirect(route('invitation.show', $plainToken));

    $this->assertGuest();

    expect(User::query()->where('email', $invitation->email)->exists())->toBeFalse()
        ->and($invitation->fresh()->accepted_at)->toBeNull()
        ->and($finished->participants()->count())->toBe(1)
        ->and(CompetitionMatch::query()->where('competition_id', $finished->id)->count())->toBe(0);
});

test('an invitation for a draft competition cannot be accepted yet', function () {
    // Eén lijn: aanmelden kan alleen op een actieve competitie, of je nu een
    // account hebt of niet. Een uitnodiging die tijdens de conceptfase is
    // verstuurd wacht tot de beheerder de competitie actief zet.
    $draft = Competition::factory()->draft()->create();
    $draft->participants()->attach(User::factory()->participant()->create());

    [$invitation, $plainToken] = createInvitation([
        'competition_id' => $draft->id,
        'role' => UserRole::Participant,
    ]);

    $this->post(route('invitation.store', $plainToken), [
        'name' => 'Nieuwe Deelnemer',
        'password' => 'nieuw-wachtwoord',
        'password_confirmation' => 'nieuw-wachtwoord',
    ])->assertRedirect(route('invitation.show', $plainToken));

    $this->assertGuest();

    expect(User::query()->where('email', $invitation->email)->exists())->toBeFalse()
        ->and($invitation->fresh()->accepted_at)->toBeNull()
        ->and($draft->participants()->count())->toBe(1)
        ->and(CompetitionMatch::query()->where('competition_id', $draft->id)->count())->toBe(0);
});

test('an invitation for an existing account asks them to sign in', function () {
    $competition = Competition::factory()->create();

    [$invitation, $plainToken] = createInvitation([
        'competition_id' => $competition->id,
        'role' => UserRole::Participant,
    ]);

    User::factory()->participant()->create(['email' => $invitation->email]);

    $this->get(route('invitation.show', $plainToken))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/accept-invitation')
            ->where('invitationState', 'sign-in')
            ->where('email', $invitation->email)
            ->where('competitionSlug', $competition->slug)
            ->where('competitionName', $competition->name)
            ->missing('passwordRules'),
        );

    // Na het inloggen hoort hij bij deze competitie uit te komen en niet op
    // het algemene dashboard.
    expect(session('url.intended'))->toBe(route('competition.dashboard', $competition));
});

test('an admin invitation for an existing account asks them to sign in without a competition', function () {
    [$invitation, $plainToken] = createInvitation();

    User::factory()->create(['email' => $invitation->email]);

    $this->get(route('invitation.show', $plainToken))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('invitationState', 'sign-in')
            ->where('competitionSlug', null),
        );
});

test('a finished competition wins over the sign in state', function () {
    // De reden waarom het niet kan is nuttiger dan "log in".
    $finished = Competition::factory()->finished()->create();

    [$invitation, $plainToken] = createInvitation([
        'competition_id' => $finished->id,
        'role' => UserRole::Participant,
    ]);

    User::factory()->participant()->create(['email' => $invitation->email]);

    $this->get(route('invitation.show', $plainToken))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('invitationState', 'closed'));
});

test('a signed in invitee is sent to the registration page', function () {
    $competition = Competition::factory()->create();

    [$invitation, $plainToken] = createInvitation([
        'competition_id' => $competition->id,
        'role' => UserRole::Participant,
    ]);

    $invitee = User::factory()->participant()->create(['email' => $invitation->email]);

    $this->actingAs($invitee)
        ->get(route('invitation.show', $plainToken))
        ->assertRedirect(route('competition.register.show', $competition));

    // Kijken is nog geen meedoen: de koppeling ontstaat pas als hij zich
    // daadwerkelijk aanmeldt.
    expect($competition->participants()->whereKey($invitee->id)->exists())->toBeFalse()
        ->and($invitation->fresh()->accepted_at)->toBeNull();
});

test('someone logged in with another account sees the wrong account state', function () {
    $competition = Competition::factory()->create();

    [$invitation, $plainToken] = createInvitation([
        'competition_id' => $competition->id,
        'role' => UserRole::Participant,
    ]);

    User::factory()->participant()->create(['email' => $invitation->email]);
    $someoneElse = User::factory()->participant()->create();

    $this->actingAs($someoneElse)
        ->get(route('invitation.show', $plainToken))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('invitationState', 'wrong-account')
            ->where('email', $invitation->email),
        );

    // Niet stilzwijgend uitloggen: dat zou zijn eigen sessie kosten.
    $this->assertAuthenticatedAs($someoneElse);
});

test('a signed in user cannot post the accept form', function () {
    [, $plainToken] = createInvitation();

    $this->actingAs(User::factory()->participant()->create())
        ->post(route('invitation.store', $plainToken), [
            'name' => 'Nieuwe Deelnemer',
            'password' => 'nieuw-wachtwoord',
            'password_confirmation' => 'nieuw-wachtwoord',
        ])->assertRedirect(route('invitation.show', $plainToken));

    expect(User::query()->count())->toBe(1);
});

test('an invitation can be declined', function () {
    $competition = Competition::factory()->create();

    [$invitation, $plainToken] = createInvitation([
        'competition_id' => $competition->id,
        'role' => UserRole::Participant,
    ]);

    User::factory()->participant()->create(['email' => $invitation->email]);

    $this->post(route('invitation.decline', $plainToken))
        ->assertRedirect(route('invitation.show', $plainToken));

    expect($invitation->fresh()->declined_at)->not->toBeNull();

    $this->get(route('invitation.show', $plainToken))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('invitationState', 'declined'));
});

test('a declined invitation cannot be accepted', function () {
    [$invitation, $plainToken] = createInvitation();

    $invitation->forceFill(['declined_at' => now()])->save();

    $this->post(route('invitation.store', $plainToken), [
        'name' => 'Nieuwe Deelnemer',
        'password' => 'nieuw-wachtwoord',
        'password_confirmation' => 'nieuw-wachtwoord',
    ])->assertRedirect(route('invitation.show', $plainToken));

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

test('an invitee with outstanding availability elsewhere still reaches the invitation page', function () {
    // Zonder de uitnodigingsroutes op de allowlist van
    // EnsureAvailabilityIsSubmitted wordt hij weggekaapt naar het formulier
    // van die andere competitie en bereikt hij zijn uitnodiging nooit.
    $other = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $other->id]);

    $invitee = User::factory()->participant()->create();
    $other->participants()->attach($invitee, ['availability_submitted_at' => null]);

    $competition = Competition::factory()->create();
    [, $plainToken] = createInvitation([
        'competition_id' => $competition->id,
        'role' => UserRole::Participant,
        'email' => $invitee->email,
    ]);

    $this->actingAs($invitee)
        ->get(route('invitation.show', $plainToken))
        ->assertRedirect(route('competition.register.show', $competition));
});
