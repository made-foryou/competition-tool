<?php

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());
});

test('withdrawing an invitation deletes it', function () {
    $competition = Competition::factory()->create();
    $invitation = Invitation::factory()->create(['competition_id' => $competition->id]);

    $this->delete(route('competitions.invitations.destroy', [$competition, $invitation]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Invitation withdrawn.')]);

    expect(Invitation::query()->whereKey($invitation->id)->exists())->toBeFalse();
});

test('resending an invitation sends the mail again and extends the expiry', function () {
    Notification::fake();

    $competition = Competition::factory()->create();
    $invitation = Invitation::factory()->create([
        'competition_id' => $competition->id,
        'expires_at' => now()->addDay(),
    ]);

    $this->post(route('competitions.invitations.resend', [$competition, $invitation]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Invitation sent again. The previous link no longer works.')]);

    $invitations = Invitation::query()
        ->where('email', $invitation->email)
        ->where('competition_id', $competition->id)
        ->get();

    expect($invitations)->toHaveCount(1)
        ->and($invitations->first()->id)->not->toBe($invitation->id)
        ->and($invitations->first()->expires_at->isAfter($invitation->expires_at))->toBeTrue();

    // Bewijst dat de mail naar het juiste adres gaat en de nieuwe uitnodiging
    // meekrijgt: zonder deze closure slaagt de assertie ook op een mail met de
    // inmiddels verwijderde uitnodiging of een verkeerde ontvanger.
    Notification::assertSentOnDemand(
        InvitationNotification::class,
        fn (InvitationNotification $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === $invitation->email
            && $notification->invitation->is($invitations->first())
    );
});

test('an expired invitation can still be withdrawn and resent', function (string $method, string $routeName) {
    Notification::fake();

    $competition = Competition::factory()->create();
    $invitation = Invitation::factory()->expired()->create(['competition_id' => $competition->id]);

    $this->{$method}(route($routeName, [$competition, $invitation]))->assertRedirect();
})->with([
    ['delete', 'competitions.invitations.destroy'],
    ['post', 'competitions.invitations.resend'],
]);

test('resending an invitation for an email that already has an account sends the mail again', function () {
    Notification::fake();

    $competition = Competition::factory()->create();
    $invitation = Invitation::factory()->create(['competition_id' => $competition->id]);
    User::factory()->create(['email' => $invitation->email]);

    $this->post(route('competitions.invitations.resend', [$competition, $invitation]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __('Invitation sent again. The previous link no longer works.'),
        ]);

    // De mail vertelt een bestaand account dat het alleen hoeft in te loggen,
    // in plaats van een account aan te maken.
    Notification::assertSentOnDemand(
        InvitationNotification::class,
        fn (InvitationNotification $notification): bool => $notification->hasAccount,
    );

    // De oude rij is vervangen, dus de vorige link werkt niet meer.
    expect(Invitation::query()->whereKey($invitation->id)->exists())->toBeFalse()
        ->and($competition->invitations()->pending()->count())->toBe(1);
});

test('resending an invitation keeps its original role', function () {
    Notification::fake();

    $competition = Competition::factory()->create();
    $invitation = Invitation::factory()->create([
        'competition_id' => $competition->id,
        'role' => UserRole::Admin,
    ]);

    $this->post(route('competitions.invitations.resend', [$competition, $invitation]));

    $newInvitation = Invitation::query()->where('email', $invitation->email)->firstOrFail();

    expect($newInvitation->role)->toBe(UserRole::Admin);
});

test('an invitation belonging to another competition is not reachable', function (string $method, string $routeName) {
    $competitionA = Competition::factory()->create();
    $competitionB = Competition::factory()->create();
    $invitation = Invitation::factory()->create(['competition_id' => $competitionB->id]);

    $this->{$method}(route($routeName, [$competitionA, $invitation]))
        ->assertNotFound();
})->with([
    ['delete', 'competitions.invitations.destroy'],
    ['post', 'competitions.invitations.resend'],
]);

test('an already accepted invitation is not reachable', function (string $method, string $routeName) {
    $competition = Competition::factory()->create();
    $invitation = Invitation::factory()->accepted()->create(['competition_id' => $competition->id]);

    $this->{$method}(route($routeName, [$competition, $invitation]))
        ->assertNotFound();
})->with([
    ['delete', 'competitions.invitations.destroy'],
    ['post', 'competitions.invitations.resend'],
]);

test('a participant cannot withdraw or resend invitations', function (string $method, string $routeName) {
    $competition = Competition::factory()->create();
    $invitation = Invitation::factory()->create(['competition_id' => $competition->id]);

    $this->actingAs(User::factory()->participant()->create())
        ->{$method}(route($routeName, [$competition, $invitation]))
        ->assertForbidden();
})->with([
    ['delete', 'competitions.invitations.destroy'],
    ['post', 'competitions.invitations.resend'],
]);

test('inviting an email that already has a pending invitation does not duplicate it', function () {
    Notification::fake();

    $competition = Competition::factory()->create();
    Invitation::factory()->create([
        'competition_id' => $competition->id,
        'email' => 'deelnemer@example.com',
    ]);

    $this->post(route('competitions.participants.store', $competition), [
        'email' => 'deelnemer@example.com',
        'mode' => 'invite',
    ]);

    expect(Invitation::query()->where('email', 'deelnemer@example.com')->count())->toBe(1);
});

test('expired and declined invitations stay visible in the admin', function () {
    // Issue #14: ze verdwenen uit de lijst inclusief de knoppen om ze in te
    // trekken of opnieuw te versturen, terwijl de backend dat wel toestaat.
    $competition = Competition::factory()->create();

    Invitation::factory()->create([
        'competition_id' => $competition->id,
        'email' => 'openstaand@example.com',
    ]);
    Invitation::factory()->expired()->create([
        'competition_id' => $competition->id,
        'email' => 'verlopen@example.com',
    ]);
    $declined = Invitation::factory()->create([
        'competition_id' => $competition->id,
        'email' => 'afgewezen@example.com',
    ]);
    $declined->forceFill(['declined_at' => now()])->save();
    Invitation::factory()->accepted()->create([
        'competition_id' => $competition->id,
        'email' => 'meegedaan@example.com',
    ]);

    User::factory()->create(['email' => 'openstaand@example.com']);

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // De geaccepteerde valt er bewust buiten: die is deelnemer.
            ->has('pendingInvitations', 3)
            // Gesorteerd op vervaldatum, dus de verlopen uitnodiging bovenaan.
            ->where('pendingInvitations.0.email', 'verlopen@example.com')
            ->where('pendingInvitations.0.is_expired', true)
            ->where('pendingInvitations.1.email', 'openstaand@example.com')
            ->where('pendingInvitations.1.is_expired', false)
            ->where('pendingInvitations.1.is_declined', false)
            ->where('pendingInvitations.1.has_account', true)
            ->where('pendingInvitations.2.email', 'afgewezen@example.com')
            ->where('pendingInvitations.2.is_declined', true)
            ->where('pendingInvitations.2.has_account', false),
        );
});
