<?php

namespace App\Actions\Competitions;

use App\Enums\MatchStatus;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
 * `UpdateMatchResultRequest` valideert de vorm en geeft de directe veldfout,
 * maar tegen een competitie die vóór de rijlock gelezen is. Tussen dat moment
 * en het schrijven kan het format nog wijzigen (zolang er geen uitslag is,
 * ligt het niet vast, dus ook niet bij de eerste) of de status van de
 * competitie. Daarom controleert deze Action status en format opnieuw onder
 * de lock, met dezelfde regel als de request: `CompetitionSettings::isValidResult()`.
 */
class RecordMatchResult
{
    /**
     * Schrijft de scores en zet de wedstrijd op gespeeld.
     *
     * @return CompetitionMatch de opgeslagen wedstrijd, na een eventuele canonicalisatie van het paar
     *
     * @throws ValidationException wanneer de competitie intussen niet meer actief is of de uitslag niet (meer) bij het format past
     * @throws ModelNotFoundException wanneer de wedstrijd intussen door een sync verwijderd is
     */
    public function handle(CompetitionMatch $match, int $firstPlayerScore, int $secondPlayerScore): CompetitionMatch
    {
        return DB::transaction(function () use ($match, $firstPlayerScore, $secondPlayerScore): CompetitionMatch {
            // Zelfde rijlock als `MoveCompetitionMatch`,
            // `SyncCompetitionMatches` en `UpdateCompetitionSettings`: een
            // gelijktijdige deelnemersmutatie wacht tot deze uitslag
            // vastligt en ziet de wedstrijd daarna als gespeeld, en een
            // gelijktijdige formatwijziging ziet dat er een uitslag is.
            $locked = Competition::query()->whereKey($match->competition_id)->lockForUpdate()->firstOrFail();

            // `$match` komt uit de route-binding en is vóór de lock gelezen.
            // Een sync kan hem daarna nog als pending-wedstrijd verwijderd
            // hebben; opnieuw lezen levert dan een `ModelNotFoundException`
            // en dus een 404 op, in plaats van een uitslag op een rij die er
            // niet meer is.
            $fresh = CompetitionMatch::query()->whereKey($match->id)->firstOrFail();

            if (! $locked->status->allowsResultChanges()) {
                throw ValidationException::withMessages([
                    'score' => __('Results can only be changed while the competition is active.'),
                ]);
            }

            if (! $locked->settings->isValidResult($firstPlayerScore, $secondPlayerScore)) {
                throw ValidationException::withMessages([
                    'score' => $locked->settings->invalidResultMessage(),
                ]);
            }

            $fresh->fill([
                'first_player_score' => $firstPlayerScore,
                'second_player_score' => $secondPlayerScore,
            ]);

            // Los toegewezen: de status is bewust niet mass-assignable.
            $fresh->status = MatchStatus::Played;
            $fresh->save();

            return $fresh;
        });
    }
}
