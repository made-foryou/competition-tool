<?php

namespace App\Http\Controllers;

use App\Actions\Competitions\MoveCompetitionMatch;
use App\Http\Requests\Competitions\MoveMatchRequest;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MatchScheduleController extends Controller
{
    /**
     * Verplaatst één wedstrijd naar een andere speeldag, tafel en tijdslot.
     *
     * De statusguard en de gespeeld-check staan in `MoveMatchRequest`; de harde
     * randvoorwaarden in `MoveCompetitionMatch`, die bij een schending een
     * veldfout op `starts_at` gooit. Te weinig rust is toegestaan en levert
     * hier een waarschuwing op in plaats van een fout: de beheerder heeft de
     * plek bewust gekozen, maar mag het niet over het hoofd zien.
     */
    public function update(MoveMatchRequest $request, Competition $competition, CompetitionMatch $match, MoveCompetitionMatch $moveCompetitionMatch): RedirectResponse
    {
        $restViolated = $moveCompetitionMatch->handle(
            $match,
            $request->integer('match_day_id'),
            $request->integer('match_day_field_id'),
            $request->string('starts_at')->toString(),
        );

        Inertia::flash('toast', $restViolated
            ? ['type' => 'warning', 'message' => __('Match moved and pinned. Note: the minimum rest time is not met.')]
            : ['type' => 'success', 'message' => __('Match moved and pinned.')]);

        return back();
    }
}
