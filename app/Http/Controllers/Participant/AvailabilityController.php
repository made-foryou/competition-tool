<?php

namespace App\Http\Controllers\Participant;

use App\Concerns\SummarizesCompetition;
use App\Concerns\SyncsAvailability;
use App\Enums\CompetitionStatus;
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

    /**
     * `availabilityState` bepaalt of het formulier op de pagina bewerkbaar
     * is: alleen `'open'` mag schrijven, de rest is read-only. De precedence
     * ligt vast: `'not-participating'` wint altijd van de status, ongeacht of
     * de competitie actief, concept of afgerond is. In de praktijk treft dat
     * alleen een admin die meekijkt -- `EnsureUserParticipatesInCompetition`
     * laat die door zonder deelnemer te zijn. De pagina toont in die staat
     * bewust geen speeldagenlijst: lege vinkjes van iemand die niet meedoet
     * zouden lezen als "deze persoon was nergens beschikbaar".
     */
    public function edit(Request $request, Competition $competition): Response
    {
        $user = $request->user();

        $availabilityState = ! $competition->hasParticipant($user)
            ? 'not-participating'
            : match ($competition->status) {
                CompetitionStatus::Active => 'open',
                CompetitionStatus::Draft => 'upcoming',
                CompetitionStatus::Finished => 'closed',
            };

        return Inertia::render('participant/availability', [
            'competition' => $this->competitionSummary($competition),
            'matchDays' => $this->availabilityProps($user, $competition),
            'hasSubmitted' => $user->hasSubmittedAvailabilityFor($competition),
            'availabilityState' => $availabilityState,
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
