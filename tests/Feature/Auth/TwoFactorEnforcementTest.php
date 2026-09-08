<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a user without a second factor is redirected to the forced setup page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('two-factor.setup'));
});

test('a user with totp can use the application', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('a user with only a passkey can use the application', function () {
    $user = User::factory()->withPasskey()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('the setup page renders for a user without a second factor', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.setup'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/two-factor-setup')
            ->has('requiresConfirmation'),
        );
});

test('the setup page redirects users who already have a second factor', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.setup'))
        ->assertRedirect(route('dashboard'));
});

test('a user without a second factor can log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect();

    $this->assertGuest();
});

test('settings are blocked for a user without a second factor', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertRedirect(route('two-factor.setup'));
});

test('guests are not affected by the two factor requirement', function () {
    $this->get(route('login'))->assertOk();
});

test('a participant without a second factor is not forced into two factor setup', function () {
    $user = User::factory()->participant()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();
});

test('an admin without a second factor is still forced into two factor setup', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertRedirect(route('two-factor.setup'));
});
