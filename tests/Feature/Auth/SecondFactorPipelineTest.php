<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('password login of a passkey-only user redirects to the passkey challenge', function () {
    $user = User::factory()->withPasskey()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.passkey'));
    $this->assertGuest();
    expect(session('login.id'))->toBe($user->id);
});

test('password login of a totp user still redirects to the two factor challenge', function () {
    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('a user with totp and a passkey is offered the passkey option on the challenge page', function () {
    $user = User::factory()->withTwoFactor()->withPasskey()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->get(route('two-factor.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/two-factor-challenge')
            ->where('hasPasskeys', true),
        );
});

test('password login without any second factor authenticates directly', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('the passkey challenge page requires a challenged login session', function () {
    $this->get(route('two-factor.passkey'))
        ->assertRedirect(route('login'));
});

test('the passkey challenge page renders for a challenged user', function () {
    $user = User::factory()->withPasskey()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->get(route('two-factor.passkey'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/two-factor-passkey'),
        );
});

test('password login marks the password as confirmed', function () {
    $user = User::factory()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect(session('auth.password_confirmed_at'))->not->toBeNull();
});
