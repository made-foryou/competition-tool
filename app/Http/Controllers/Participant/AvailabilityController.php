<?php

namespace App\Http\Controllers\Participant;

use App\Concerns\SummarizesCompetition;
use App\Concerns\SyncsAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Participant\UpdateAvailabilityRequest;
use App\Models\Competition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De deelnemer geeft hier aan op welke speeldagen hij aanwezig is. Zolang hij
 * dat niet heeft gedaan houdt EnsureAvailabilityIsSubmitted hem op deze pagina.
 */
class AvailabilityController extends Controller
{
    use SummarizesCompetition;
    use SyncsAvailability;

    public function edit(Request $request, Competition $competition): Response
    {
        $user = $request->user();

        return Inertia::render('participant/availability', [
            'competition' => $this->competitionSummary($competition),
            'matchDays' => $this->availabilityProps($user, $competition),
            'hasSubmitted' => $user->hasSubmittedAvailabilityFor($competition),
        ]);
    }

    public function update(UpdateAvailabilityRequest $request, Competition $competition): RedirectResponse
    {
        $user = $request->user();
        $wasSubmitted = $user->hasSubmittedAvailabilityFor($competition);

        $this->syncAvailability($user, $competition, $request->validated('match_days') ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Availability saved.')]);

        return $wasSubmitted
            ? to_route('competition.availability.edit', $competition)
            : to_route('competition.dashboard', $competition);
    }
}
