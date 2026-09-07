<?php

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;

test('a user can be invited via the artisan command', function () {
    Notification::fake();

    $this->artisan('app:invite', ['email' => 'nieuw@made.nl'])
        ->assertSuccessful();

    expect(Invitation::query()->where('email', 'nieuw@made.nl')->exists())->toBeTrue();

    Notification::assertSentOnDemand(
        InvitationNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'nieuw@made.nl',
    );
});

test('an existing user cannot be invited', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->artisan('app:invite', ['email' => $user->email])
        ->assertFailed();

    expect(Invitation::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('an invalid email address is rejected', function () {
    Notification::fake();

    $this->artisan('app:invite', ['email' => 'geen-email'])
        ->assertFailed();

    expect(Invitation::query()->count())->toBe(0);
});

test('a new invitation replaces a pending one for the same email', function () {
    Notification::fake();

    $existing = Invitation::factory()->create(['email' => 'nieuw@made.nl']);

    $this->artisan('app:invite', ['email' => 'nieuw@made.nl'])
        ->assertSuccessful();

    expect(Invitation::query()->where('email', 'nieuw@made.nl')->count())->toBe(1)
        ->and(Invitation::query()->whereKey($existing->id)->exists())->toBeFalse();
});
