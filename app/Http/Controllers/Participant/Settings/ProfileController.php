<?php

namespace App\Http\Controllers\Participant\Settings;

use App\Concerns\SummarizesCompetition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Competition;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Naam en e-mailadres van de deelnemer, binnen het deelnemersgedeelte.
 */
class ProfileController extends Controller
{
    use SummarizesCompetition;

    public function edit(Competition $competition): Response
    {
        return Inertia::render('participant/settings/profile', [
            'competition' => $this->competitionSummary($competition),
        ]);
    }

    public function update(ProfileUpdateRequest $request, Competition $competition): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('competition.settings.profile.edit', $competition);
    }
}
