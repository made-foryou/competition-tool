<?php

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;

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

    Notification::assertSentOnDemand(InvitationNotification::class);

    $invitations = Invitation::query()->where('email', $invitation->email)->get();

    expect($invitations)->toHaveCount(1)
        ->and($invitations->first()->id)->not->toBe($invitation->id)
        ->and($invitations->first()->expires_at->isAfter($invitation->expires_at))->toBeTrue();
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
