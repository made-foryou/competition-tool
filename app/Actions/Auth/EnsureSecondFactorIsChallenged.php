<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login-pipelinestap die naast TOTP ook passkeys als tweede factor afdwingt.
 *
 * Fortify's eigen RedirectIfTwoFactorAuthenticatable kijkt alleen naar een
 * bevestigd TOTP-secret; een gebruiker met alleen een passkey zou daardoor na
 * een wachtwoord-login zonder tweede stap binnenkomen.
 */
class EnsureSecondFactorIsChallenged extends RedirectIfTwoFactorAuthenticatable
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @param  callable  $next
     */
    public function handle($request, $next): mixed
    {
        $user = $this->validateCredentials($request);

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return $this->twoFactorChallengeResponse($request, $user);
        }

        if ($user->hasPasskeysEnabled()) {
            return $this->passkeyChallengeResponse($request, $user);
        }

        return $next($request);
    }

    /**
     * Stuur de gebruiker naar de passkey-challenge zonder in te loggen.
     *
     * @param  Request  $request
     */
    protected function passkeyChallengeResponse($request, User $user): Response
    {
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $request->boolean('remember'),
        ]);

        return $request->wantsJson()
            ? response()->json(['two_factor' => true])
            : redirect()->route('two-factor.passkey');
    }
}
