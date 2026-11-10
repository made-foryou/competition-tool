<?php

namespace App\Actions\Competitions;

use App\Enums\MatchStatus;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Legt de uitslag van één wedstrijd vast, of corrigeert een eerder
 * ingevoerde uitslag: de gewonnen games per speler in de score-kolommen en
 * de status op `Played`.
 *
 * De scores komen binnen per kolom, niet per "speler links/rechts uit het
 * formulier": `first_player_score` hoort bij `first_player_id`. Mocht de rij
 * nog niet canoniek zijn, dan wisselt de `saving`-hook van het model spelers
 * en scores samen om, zodat elke score bij zijn speler blijft.
 *
 * De domeinvalidatie (geheel getal tussen 0 en het aantal games om te
 * winnen, precies één winnaar) zit in `UpdateMatchResultRequest`; die hangt
 * alleen af van het format, en dat ligt vast zodra er een uitslag is.
 */
class RecordMatchResult
{
    /**
     * Schrijft de scores en zet de wedstrijd op gespeeld.
     *
     * @throws ModelNotFoundException wanneer de wedstrijd intussen door een sync verwijderd is
     */
    public function handle(CompetitionMatch $match, int $firstPlayerScore, int $secondPlayerScore): void
    {
        DB::transaction(function () use ($match, $firstPlayerScore, $secondPlayerScore): void {
            // Zelfde rijlock als `MoveCompetitionMatch` en
            // `SyncCompetitionMatches`: een gelijktijdige deelnemersmutatie
            // wacht tot deze uitslag vastligt, en ziet de wedstrijd daarna
            // als gespeeld.
            Competition::query()->whereKey($match->competition_id)->lockForUpdate()->firstOrFail();

            // `$match` komt uit de route-binding en is vóór de lock gelezen.
            // Een sync kan hem daarna nog als pending-wedstrijd verwijderd
            // hebben; opnieuw lezen levert dan een `ModelNotFoundException`
            // en dus een 404 op, in plaats van een uitslag op een rij die er
            // niet meer is.
            $fresh = CompetitionMatch::query()->whereKey($match->id)->firstOrFail();

            $fresh->fill([
                'first_player_score' => $firstPlayerScore,
                'second_player_score' => $secondPlayerScore,
            ]);

            // Los toegewezen: de status is bewust niet mass-assignable.
            $fresh->status = MatchStatus::Played;
            $fresh->save();
        });
    }
}
