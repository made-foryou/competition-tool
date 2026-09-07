<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasskeyChallengeController extends Controller
{
    /**
     * Toon de passkey-challenge voor een gebruiker die met e-mail en
     * wachtwoord is ingelogd maar alleen een passkey als tweede factor heeft.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/two-factor-passkey');
    }
}
