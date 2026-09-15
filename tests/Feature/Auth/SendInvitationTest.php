<?php

use App\Actions\Auth\SendInvitation;
use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\Notification;

test('a second invitation for the same email but a different competition leaves the first intact', function () {
    Notification::fake();

    $competitionA = Competition::factory()->create();
    $competitionB = Competition::factory()->create();

    app(SendInvitation::class)->handle('deelnemer@example.com', UserRole::Participant, $competitionA);
    app(SendInvitation::class)->handle('deelnemer@example.com', UserRole::Participant, $competitionB);

    expect(Invitation::query()->where('email', 'deelnemer@example.com')->count())->toBe(2)
        ->and(Invitation::query()->where('competition_id', $competitionA->id)->exists())->toBeTrue()
        ->and(Invitation::query()->where('competition_id', $competitionB->id)->exists())->toBeTrue();
});

test('a second invitation for the same email and the same competition replaces the first', function () {
    Notification::fake();

    $competition = Competition::factory()->create();

    app(SendInvitation::class)->handle('deelnemer@example.com', UserRole::Participant, $competition);
    $firstInvitationId = Invitation::query()->where('email', 'deelnemer@example.com')->firstOrFail()->id;

    app(SendInvitation::class)->handle('deelnemer@example.com', UserRole::Participant, $competition);

    $invitations = Invitation::query()->where('email', 'deelnemer@example.com')->get();

    expect($invitations)->toHaveCount(1)
        ->and($invitations->first()->id)->not->toBe($firstInvitationId);
});

test('a second admin invitation for the same email replaces the first', function () {
    Notification::fake();

    app(SendInvitation::class)->handle('beheerder@example.com', UserRole::Admin);
    $firstInvitationId = Invitation::query()->where('email', 'beheerder@example.com')->firstOrFail()->id;

    app(SendInvitation::class)->handle('beheerder@example.com', UserRole::Admin);

    $invitations = Invitation::query()->where('email', 'beheerder@example.com')->get();

    expect($invitations)->toHaveCount(1)
        ->and($invitations->first()->id)->not->toBe($firstInvitationId)
        ->and($invitations->first()->competition_id)->toBeNull();
});

test('an admin invitation does not touch a pending participant invitation for the same email', function () {
    Notification::fake();

    $competition = Competition::factory()->create();

    app(SendInvitation::class)->handle('dubbelrol@example.com', UserRole::Participant, $competition);
    app(SendInvitation::class)->handle('dubbelrol@example.com', UserRole::Admin);

    expect(Invitation::query()->where('email', 'dubbelrol@example.com')->count())->toBe(2);

    Notification::assertSentOnDemandTimes(InvitationNotification::class, 2);
});

test('token and accepted_at are not mass-assignable on invitation', function () {
    expect(fn () => (new Invitation)->fill([
        'email' => 'deelnemer@example.com',
        'token' => 'gekozen-token',
        'accepted_at' => now(),
    ]))->toThrow(MassAssignmentException::class, 'token, accepted_at');
});

test('handle records the inviter and the competition', function () {
    Notification::fake();

    $inviter = User::factory()->create();
    $competition = Competition::factory()->create();

    app(SendInvitation::class)->handle('deelnemer@example.com', UserRole::Participant, $competition, $inviter);

    $invitation = Invitation::query()->where('email', 'deelnemer@example.com')->firstOrFail();

    expect($invitation->invited_by)->toBe($inviter->id)
        ->and($invitation->competition_id)->toBe($competition->id);
});

test('handle stores the hashed token and leaves accepted_at null', function () {
    Notification::fake();

    $plainToken = app(SendInvitation::class)->handle('deelnemer@example.com', UserRole::Participant);

    $invitation = Invitation::query()->where('email', 'deelnemer@example.com')->firstOrFail();

    expect($invitation->token)->toBe(hash('sha256', $plainToken))
        ->and($invitation->accepted_at)->toBeNull();
});

test('an invitation is valid for thirty days', function () {
    Notification::fake();

    app(SendInvitation::class)->handle('nieuw@example.com', UserRole::Participant);

    $invitation = Invitation::query()->where('email', 'nieuw@example.com')->firstOrFail();

    expect($invitation->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();
});

test('the notification knows whether the email already has an account', function () {
    Notification::fake();

    User::factory()->create(['email' => 'bestaat@example.com']);

    app(SendInvitation::class)->handle('bestaat@example.com', UserRole::Participant);
    app(SendInvitation::class)->handle('nieuw@example.com', UserRole::Participant);

    Notification::assertSentOnDemand(
        InvitationNotification::class,
        fn (InvitationNotification $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'bestaat@example.com'
            && $notification->hasAccount,
    );

    Notification::assertSentOnDemand(
        InvitationNotification::class,
        fn (InvitationNotification $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'nieuw@example.com'
            && ! $notification->hasAccount,
    );
});

test('the invitation email for an existing account asks them to log in', function () {
    $competition = Competition::factory()->create(['name' => 'Voorjaarstoernooi']);
    $invitation = Invitation::factory()->create([
        'competition_id' => $competition->id,
        'email' => 'bestaat@example.com',
    ]);

    $mail = (new InvitationNotification($invitation, 'plain-token', true))->toMail(new stdClass);

    expect($mail->actionText)->toBe(__('Log in and sign up'))
        ->and($mail->introLines)->toContain(__('You already have an account for :email, so you only have to log in.', [
            'email' => 'bestaat@example.com',
        ]));

    $forNewAccount = (new InvitationNotification($invitation, 'plain-token'))->toMail(new stdClass);

    expect($forNewAccount->actionText)->toBe(__('Accept invitation'));
});
