<?php

use App\Http\Responses\LoginResponse;
use App\Models\Competition;
use App\Models\User;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

test('admins are redirected to the admin dashboard after login', function () {
    // Admin zonder 2FA: die doorloopt de pipeline zonder challenge en raakt
    // direct de LoginResponse (de 2FA-setup-redirect gebeurt pas daarna via
    // de EnsureTwoFactorIsConfigured-middleware, niet in de login-redirect).
    $admin = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});

test('participants are redirected to their most recent active competition', function () {
    $user = User::factory()->participant()->create();
    $old = Competition::factory()->create(['starts_at' => now()->subMonth()->toDateString()]);
    $recent = Competition::factory()->create(['starts_at' => now()->toDateString()]);
    $finished = Competition::factory()->finished()->create(['starts_at' => now()->addDay()->toDateString()]);
    $user->competitions()->attach([$old->id, $recent->id, $finished->id]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('competition.dashboard', $recent));
});

test('participants without an active competition see the no-competition page', function () {
    $user = User::factory()->participant()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('competition.none'));

    $this->get(route('competition.none'))->assertOk();
});

test('logging in via the competition login page returns to that competition', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->get(route('competition.login', $competition));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('competition.dashboard', $competition));
});

test('the custom login response is bound for both login contracts', function () {
    expect(app(LoginResponseContract::class))->toBeInstanceOf(LoginResponse::class)
        ->and(app(TwoFactorLoginResponseContract::class))->toBeInstanceOf(LoginResponse::class);
});
