<?php

namespace App\Actions\Auth;

use Illuminate\Http\Request;

/**
 * Login-pipelinestap die het zojuist getypte wachtwoord als bevestigd
 * markeert, zodat de gebruiker op de verplichte 2FA-setup niet direct
 * opnieuw langs de password.confirm-middleware moet.
 */
class MarkPasswordAsConfirmed
{
    public function handle(Request $request, callable $next): mixed
    {
        $request->session()->put('auth.password_confirmed_at', time());

        return $next($request);
    }
}
