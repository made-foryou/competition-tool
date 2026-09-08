<?php

use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasskeyLoginResponse;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

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

test('the passkey login contract is bound to its own response class', function () {
    expect(app(PasskeyLoginResponseContract::class))->toBeInstanceOf(PasskeyLoginResponse::class);
});

/**
 * De volledige passkey-flow vereist een echte WebAuthn-credential en is te
 * duur om end-to-end te testen. Deze tests roepen de responseklasse daarom
 * rechtstreeks aan, met een JSON-request waarop de gebruiker is gezet — en
 * asserten op de daadwerkelijke JSON-inhoud (de `redirect`-sleutel), precies
 * wat er stuk was: de oude binding gaf `{'two_factor': false}` terug en
 * bereikte de redirect-logica nooit.
 *
 * `redirect()->intended()` leest de sessie van de Redirector, die pas gezet
 * wordt door de StartSession-middleware tijdens een echte HTTP-request.
 * Vandaar dat elke test eerst een lichte HTTP-call doet voordat de
 * responseklasse direct wordt aangeroepen.
 */
function passkeyJsonRequestFor(User $user): Request
{
    $request = Request::create('/two-factor-challenge/passkey', 'POST');
    $request->headers->set('Accept', 'application/json');
    $request->setUserResolver(fn () => $user);

    return $request;
}

test('an admin receives a redirect to the admin dashboard from the passkey response', function () {
    $admin = User::factory()->create();

    $this->get(route('home'));

    $response = (new PasskeyLoginResponse)->toResponse(passkeyJsonRequestFor($admin));

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true))->toBe(['redirect' => route('dashboard')]);
});

test('a participant with an active competition receives a redirect to that competition from the passkey response', function () {
    $user = User::factory()->participant()->create();
    $competition = Competition::factory()->create(['starts_at' => now()->toDateString()]);
    $user->competitions()->attach($competition);

    $this->get(route('home'));

    $response = (new PasskeyLoginResponse)->toResponse(passkeyJsonRequestFor($user));

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true))->toBe(['redirect' => route('competition.dashboard', $competition)]);
});

test('a participant without an active competition receives a redirect to the no-competition page from the passkey response', function () {
    $user = User::factory()->participant()->create();

    $this->get(route('home'));

    $response = (new PasskeyLoginResponse)->toResponse(passkeyJsonRequestFor($user));

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true))->toBe(['redirect' => route('competition.none')]);
});

test('a set intended url wins over the role-based destination in the passkey response', function () {
    $admin = User::factory()->create();
    $competition = Competition::factory()->create();

    // Zet de intended-url zoals de competitie-loginpagina dat doet, en
    // primet meteen de sessie op de Redirector via deze echte HTTP-call.
    $this->get(route('competition.login', $competition));

    $response = (new PasskeyLoginResponse)->toResponse(passkeyJsonRequestFor($admin));

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true))->toBe(['redirect' => route('competition.dashboard', $competition)]);
});
