<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class TwoFactorSetupController extends Controller
{
    /**
     * Toon de verplichte 2FA-setup voor gebruikers zonder tweede factor.
     */
    public function __invoke(TwoFactorAuthenticationRequest $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasEnabledTwoFactorAuthentication() || $user->hasPasskeysEnabled()) {
            return redirect()->route('dashboard');
        }

        $request->ensureStateIsValid();

        return Inertia::render('auth/two-factor-setup', [
            'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }
}
