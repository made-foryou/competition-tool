<?php

use App\Models\Competition;
use App\Models\User;

test('a participant cannot reach the admin settings', function (string $method, string $routeName) {
    $this->actingAs(User::factory()->participant()->create())
        ->{$method}(route($routeName))
        ->assertForbidden();
})->with([
    ['get', 'profile.edit'],
    ['patch', 'profile.update'],
    ['delete', 'profile.destroy'],
    ['get', 'security.edit'],
    ['put', 'user-password.update'],
    ['get', 'appearance.edit'],
]);

test('an admin still reaches the admin settings', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(route('profile.edit'))
        ->assertOk();
});

test('the passkey discovery endpoint points at the role independent entry point', function () {
    $this->get(route('well-known.passkeys'))
        ->assertOk()
        ->assertJson([
            'enroll' => route('passkey.manage'),
            'manage' => route('passkey.manage'),
        ]);
});

test('passkey management sends an admin to the admin settings', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(route('passkey.manage'))
        ->assertRedirect(route('security.edit'));
});

test('passkey management sends a participant to their own competition settings', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('passkey.manage'))
        ->assertRedirect(route('competition.settings.security.edit', $competition));
});

test('passkey management sends a participant without a competition to the empty state', function () {
    $this->actingAs(User::factory()->participant()->create())
        ->get(route('passkey.manage'))
        ->assertRedirect(route('competition.none'));
});
