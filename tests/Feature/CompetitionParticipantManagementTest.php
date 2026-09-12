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

test('an existing user is linked directly by email', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => $user->email,
    ])->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Participant linked.')]);

    expect($competition->participants()->whereKey($user->id)->exists())->toBeTrue();
});

test('linking an existing admin keeps the admin role', function () {
    $competition = Competition::factory()->create();
    $admin = User::factory()->withTwoFactor()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => $admin->email,
    ]);

    expect($admin->refresh()->role)->toBe(UserRole::Admin)
        ->and($competition->participants()->whereKey($admin->id)->exists())->toBeTrue();
});

test('linking the same user twice does not fail or duplicate', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->post(route('competitions.participants.store', $competition), [
        'email' => $user->email,
    ])->assertRedirect();

    expect($competition->participants()->count())->toBe(1);
});

test('an unknown email with invite mode sends a participant invitation', function () {
    Notification::fake();
    $competition = Competition::factory()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => 'nieuw@example.com',
        'mode' => 'invite',
    ])->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

    $invitation = Invitation::query()->where('email', 'nieuw@example.com')->firstOrFail();
    expect($invitation->role)->toBe(UserRole::Participant)
        ->and($invitation->competition_id)->toBe($competition->id);

    Notification::assertSentOnDemand(InvitationNotification::class);
});

test('an unknown email with create mode creates a verified participant account', function () {
    $competition = Competition::factory()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => 'direct@example.com',
        'mode' => 'create',
        'name' => 'Directe Deelnemer',
        'password' => 'SuperSecret123!',
    ])->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Participant added.')]);

    $user = User::query()->where('email', 'direct@example.com')->firstOrFail();
    expect($user->role)->toBe(UserRole::Participant)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($competition->participants()->whereKey($user->id)->exists())->toBeTrue();
});

test('create mode requires a name and password', function () {
    $competition = Competition::factory()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => 'direct@example.com',
        'mode' => 'create',
    ])->assertSessionHasErrors(['name', 'password']);
});

test('an unknown email without a mode is rejected', function () {
    $competition = Competition::factory()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => 'nieuw@example.com',
    ])->assertSessionHasErrors('mode');
});

test('a participant can be detached', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->delete(route('competitions.participants.destroy', [$competition, $user]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Participant removed.')]);

    expect($competition->participants()->count())->toBe(0)
        ->and(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

test('participants cannot manage the participant list', function () {
    $competition = Competition::factory()->create();

    $this->actingAs(User::factory()->participant()->create())
        ->post(route('competitions.participants.store', $competition), [
            'email' => 'x@example.com',
            'mode' => 'invite',
        ])->assertForbidden();
});
