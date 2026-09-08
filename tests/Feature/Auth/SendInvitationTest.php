<?php

use App\Actions\Auth\SendInvitation;
use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\Invitation;
use App\Notifications\InvitationNotification;
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
