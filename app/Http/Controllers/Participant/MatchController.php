<?php

namespace App\Http\Controllers\Participant;

use App\Concerns\SummarizesCompetition;
use App\Concerns\SummarizesParticipantMatches;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De pagina "Mijn wedstrijden" van de deelnemer: de volledige lijst van zijn
 * eigen wedstrijden in deze competitie. Een beheerder die niet aan de
 * competitie gekoppeld is, mag de pagina bekijken maar krijgt geen lijst.
 */
class MatchController extends Controller
{
    use SummarizesCompetition;
    use SummarizesParticipantMatches;

    public function __invoke(Request $request, Competition $competition): Response
    {
        $user = $request->user();
        $isParticipant = $competition->hasParticipant($user);

        return Inertia::render('participant/matches', [
            'competition' => [...$this->competitionSummary($competition), 'status' => $competition->status->value],
            'isParticipant' => $isParticipant,
            'matches' => $isParticipant
                ? Inertia::defer(fn (): array => $this->participantMatchRows($user, $competition))
                : null,
        ]);
    }
}
