<?php

namespace App\Http\Controllers\Participant;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class CompetitionLoginController extends Controller
{
    /**
     * Toon de competitie-loginpagina en zet de intended-url zodat de
     * bestaande Fortify-pipeline na inloggen terugstuurt naar de competitie.
     * Alleen als er nog geen intended-url staat: wie via een diepe link hier
     * belandt -- het beschikbaarheidsformulier, een uitnodiging -- hoort daar
     * na het inloggen ook uit te komen, niet op het dashboard.
     */
    public function __invoke(Request $request, Competition $competition): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('competition.dashboard', $competition);
        }

        if (! $request->session()->has('url.intended')) {
            $request->session()->put('url.intended', route('competition.dashboard', $competition));
        }

        return Inertia::render('auth/competition-login', [
            'competitionName' => $competition->name,
            'competitionSlug' => $competition->slug,
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]);
    }
}
