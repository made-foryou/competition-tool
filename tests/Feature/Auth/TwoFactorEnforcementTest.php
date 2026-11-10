<?php

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

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
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.settings.profile.edit', $competition))
        ->assertOk();
});

test('an admin without a second factor is still forced into two factor setup', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertRedirect(route('two-factor.setup'));
});

test('confirming totp over json responds without a redirect', function () {
    $user = User::factory()->create();
    app(EnableTwoFactorAuthentication::class)($user);
    $code = app(Google2FA::class)->getCurrentOtp(
        Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret),
    );

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->postJson(route('two-factor.confirm'), ['code' => $code])
        ->assertOk();

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('an invalid totp code over json returns the error under code', function () {
    $user = User::factory()->create();
    app(EnableTwoFactorAuthentication::class)($user);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->postJson(route('two-factor.confirm'), ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

test('recovery codes fetched during setup stay valid after confirming, even for an admin participant with pending availability', function () {
    $competition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);
    $admin = User::factory()->create();
    $competition->participants()->attach($admin);
    app(EnableTwoFactorAuthentication::class)($admin);
    $code = app(Google2FA::class)->getCurrentOtp(
        Fortify::currentEncrypter()->decrypt($admin->fresh()->two_factor_secret),
    );

    $fetched = $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->getJson(route('two-factor.recovery-codes'))
        ->assertOk()
        ->assertJsonCount(8)
        ->json();

    $this->postJson(route('two-factor.confirm'), ['code' => $code])->assertOk();

    expect($admin->fresh()->recoveryCodes())->toBe($fetched);

    $this->getJson(route('two-factor.recovery-codes'))
        ->assertRedirect(route('competition.availability.edit', $competition));
});
