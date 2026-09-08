<?php

namespace App\Http\Responses;

use App\Enums\CompetitionStatus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

/**
 * Rolafhankelijke redirect na inloggen: admins naar het beheer-dashboard,
 * deelnemers naar hun meest recente actieve competitie. Een intended-url
 * (zoals gezet door de competitie-loginpagina) heeft altijd voorrang.
 */
class LoginResponse implements LoginResponseContract, PasskeyLoginResponseContract, TwoFactorLoginResponseContract
{
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

    protected function defaultUrlFor(?User $user): string
    {
        if ($user === null || $user->isAdmin()) {
            return route('dashboard');
        }

        $competition = $user->competitions()
            ->where('status', CompetitionStatus::Active)
            ->orderByDesc('starts_at')
            ->first();

        return $competition !== null
            ? route('competition.dashboard', $competition)
            : route('competition.none');
    }
}
