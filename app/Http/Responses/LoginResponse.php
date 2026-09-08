<?php

namespace App\Http\Responses;

use App\Concerns\DeterminesLoginDestination;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

/**
 * Rolafhankelijke redirect na inloggen: admins naar het beheer-dashboard,
 * deelnemers naar hun meest recente actieve competitie. Een intended-url
 * (zoals gezet door de competitie-loginpagina) heeft altijd voorrang.
 *
 * Dekt de Fortify-contracten voor wachtwoord- en 2FA-login. Het passkey-pad
 * loopt buiten Fortify om via `Laravel\Passkeys\Contracts\PasskeyLoginResponse`
 * en heeft daarom zijn eigen responseklasse (`PasskeyLoginResponse`) nodig:
 * dit contract geeft voor JSON-requests altijd `['two_factor' => false]`
 * terug, terwijl de passkey-frontend juist een `redirect`-veld verwacht.
 */
class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    use DeterminesLoginDestination;

    /**
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = $request->user();

        return redirect()->intended($this->defaultUrlFor($user instanceof User ? $user : null));
    }
}
