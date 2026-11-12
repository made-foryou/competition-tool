<?php

namespace App\Http\Controllers\Participant;

use App\Actions\Competitions\BuildStandings;
use App\Concerns\SummarizesCompetition;
use App\Concerns\SummarizesStandings;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De pagina "Stand" van de deelnemer: de volledige stand van de competitie,
 * met alleen de `display_name` van de spelers. Een beheerder die niet aan de
 * competitie gekoppeld is, mag de pagina bekijken en ziet de stand ook, maar
 * zonder eigen regel.
 */
class StandingsController extends Controller
{
    use SummarizesCompetition;
    use SummarizesStandings;

    public function __invoke(Request $request, Competition $competition, BuildStandings $buildStandings): Response
    {
        return Inertia::render('participant/standings', [
            'competition' => [...$this->competitionSummary($competition), 'status' => $competition->status->value],
            'standings' => Inertia::defer(
                fn (): array => $this->standingsProps($buildStandings->handle($competition), $request->user()),
            ),
        ]);
    }
}
