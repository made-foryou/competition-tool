<?php

use App\Enums\UserRole;
use App\Models\User;

test('factory users are admins by default', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->isAdmin())->toBeTrue();
});

test('the participant factory state creates participants', function () {
    $user = User::factory()->participant()->create();

    expect($user->role)->toBe(UserRole::Participant)
        ->and($user->isAdmin())->toBeFalse();
});

test('users created without an explicit role are participants', function () {
    $user = User::create([
        'name' => 'Test',
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    expect($user->refresh()->role)->toBe(UserRole::Participant);
});
