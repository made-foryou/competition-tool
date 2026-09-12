<?php

use App\Models\Competition;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

/**
 * @return array{0: Competition, 1: User}
 */
function participantInCompetition(): array
{
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    return [$competition, $user];
}

test('a participant sees the settings overview inside the competition', function () {
    [$competition, $user] = participantInCompetition();

    $this->actingAs($user)
        ->get(route('competition.settings.index', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('participant/settings/index')
            ->where('competition.name', $competition->name)
            ->where('competition.slug', $competition->slug),
        );
});

test('the profile page carries the competition for the layout', function () {
    [$competition, $user] = participantInCompetition();

    $this->actingAs($user)
        ->get(route('competition.settings.profile.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('participant/settings/profile')
            ->where('competition.slug', $competition->slug),
        );
});

test('a participant can update their profile and stays in the participant area', function () {
    [$competition, $user] = participantInCompetition();

    $this->actingAs($user)
        ->patch(route('competition.settings.profile.update', $competition), [
            'name' => 'Nieuwe Naam',
            'email' => 'nieuw@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('competition.settings.profile.edit', $competition));

    $user->refresh();

    expect($user->name)->toBe('Nieuwe Naam')
        ->and($user->email)->toBe('nieuw@example.com')
        ->and($user->email_verified_at)->toBeNull();
});

test('a participant can change their password', function () {
    [$competition, $user] = participantInCompetition();

    $this->actingAs($user)
        ->put(route('competition.settings.password.update', $competition), [
            'current_password' => 'password',
            'password' => 'Nieuw-Wachtwoord-1',
            'password_confirmation' => 'Nieuw-Wachtwoord-1',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('competition.settings.password.edit', $competition));

    expect(Hash::check('Nieuw-Wachtwoord-1', $user->refresh()->password))->toBeTrue();
});

test('changing the password requires the current password', function () {
    [$competition, $user] = participantInCompetition();

    $this->actingAs($user)
        ->from(route('competition.settings.password.edit', $competition))
        ->put(route('competition.settings.password.update', $competition), [
            'current_password' => 'verkeerd-wachtwoord',
            'password' => 'Nieuw-Wachtwoord-1',
            'password_confirmation' => 'Nieuw-Wachtwoord-1',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

test('the security page requires password confirmation', function () {
    [$competition, $user] = participantInCompetition();

    $this->actingAs($user)
        ->get(route('competition.settings.security.edit', $competition))
        ->assertRedirect(route('password.confirm'));
});

test('the security page shows two factor and passkey options after confirmation', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    [$competition, $user] = participantInCompetition();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('competition.settings.security.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('participant/settings/security')
            ->where('competition.slug', $competition->slug)
            ->where('canManageTwoFactor', true)
            ->where('canManagePasskeys', true)
            ->where('passkeys', []),
        );
});

test('an unlinked participant cannot reach the settings', function () {
    $competition = Competition::factory()->create();

    $this->actingAs(User::factory()->participant()->create())
        ->get(route('competition.settings.index', $competition))
        ->assertForbidden();
});

test('guests are sent to the competition login page', function () {
    $competition = Competition::factory()->create();

    $this->get(route('competition.settings.index', $competition))
        ->assertRedirect(route('competition.login', $competition));
});

test('settings of a draft competition are hidden', function () {
    $competition = Competition::factory()->draft()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.settings.index', $competition))
        ->assertNotFound();
});
