<?php

namespace App\Http\Responses;

use App\Concerns\DeterminesLoginDestination;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rolafhankelijke redirect na passkey-login. Het passkey-pad van
 * laravel/passkeys loopt buiten de Fortify-pipeline om en verwacht daarom
 * het responseformaat van de vendor-default (`redirect`-sleutel in de
 * JSON-body, zie `Laravel\Passkeys\Http\Responses\PasskeyLoginResponse`),
 * niet dat van `Laravel\Fortify\Contracts\LoginResponse`. De bestemming
 * zelf komt uit dezelfde `DeterminesLoginDestination`-trait als
 * {@see LoginResponse}, zodat wachtwoord-, 2FA- en passkey-login altijd
 * naar dezelfde plek sturen.
 */
class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    use DeterminesLoginDestination;

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $user = $request->user();
        $destination = $this->defaultUrlFor($user instanceof User ? $user : null);

        if ($request->wantsJson()) {
            return new JsonResponse([
                'redirect' => redirect()->intended($destination)->getTargetUrl(),
            ], 200);
        }

        return redirect()->intended($destination);
    }
}
