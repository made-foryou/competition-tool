<?php

namespace App\Http\Controllers;

use App\Actions\Competitions\ClearMatchResult;
use App\Actions\Competitions\RecordMatchResult;
use App\Http\Requests\Competitions\DestroyMatchResultRequest;
use App\Http\Requests\Competitions\UpdateMatchResultRequest;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MatchResultController extends Controller
{
    /**
     * Legt de uitslag van een wedstrijd vast of corrigeert hem. De statusguard
     * en het format staan in `UpdateMatchResultRequest`, en opnieuw onder de
     * rijlock in `RecordMatchResult`.
     *
     * De toast noemt spelers en scores uit de opgeslagen wedstrijd, niet uit
     * het verzoek: na een canonicalisatie van het paar staan ze daar in de
     * volgorde van de wedstrijdenlijst.
     */
    public function update(UpdateMatchResultRequest $request, Competition $competition, CompetitionMatch $match, RecordMatchResult $recordMatchResult): RedirectResponse
    {
        $saved = $recordMatchResult->handle(
            $match,
            $request->integer('first_player_score'),
            $request->integer('second_player_score'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Result saved: :first – :second :first_score–:second_score.', [
            'first' => $saved->firstPlayer->display_name,
            'second' => $saved->secondPlayer->display_name,
            'first_score' => $saved->first_player_score,
            'second_score' => $saved->second_player_score,
        ])]);

        return back();
    }

    /**
     * Wist de uitslag. De wedstrijd kan daarbij verdwijnen als een van de
     * spelers niet meer deelneemt; de toast zegt dat er dan bij.
     */
    public function destroy(DestroyMatchResultRequest $request, Competition $competition, CompetitionMatch $match, ClearMatchResult $clearMatchResult): RedirectResponse
    {
        $matchStillExists = $clearMatchResult->handle($match);

        Inertia::flash('toast', ['type' => 'success', 'message' => $matchStillExists
            ? __('Result cleared.')
            : __('Result cleared. The match was removed because one of its players no longer participates.')]);

        return back();
    }
}
