<?php

namespace App\Http\Controllers;

use App\Http\Requests\Competitions\PinMatchRequest;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MatchPinController extends Controller
{
    /**
     * Zet een geplande wedstrijd vast, zodat de eerstvolgende herplanning hem
     * op zijn plek laat staan.
     *
     * Schrijft via de query builder omdat `pinned_at` bewust niet
     * mass-assignable is.
     */
    public function store(PinMatchRequest $request, Competition $competition, CompetitionMatch $match): RedirectResponse
    {
        CompetitionMatch::query()->whereKey($match->id)->update(['pinned_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Match pinned.')]);

        return back();
    }

    /**
     * Maakt een wedstrijd weer los: hij houdt zijn huidige plek, maar de
     * eerstvolgende herplanning mag hem verplaatsen.
     */
    public function destroy(PinMatchRequest $request, Competition $competition, CompetitionMatch $match): RedirectResponse
    {
        CompetitionMatch::query()->whereKey($match->id)->update(['pinned_at' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Match unpinned.')]);

        return back();
    }
}
