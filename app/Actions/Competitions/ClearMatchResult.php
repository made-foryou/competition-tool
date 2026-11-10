<?php

namespace App\Actions\Competitions;

use App\Enums\MatchStatus;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Wist de uitslag van één wedstrijd: de scores gaan leeg en de status terug
 * op `Pending`.
 *
 * Daarmee valt de wedstrijd weer onder `SyncCompetitionMatches`, en die
 * draait hier direct na het wissen. Is een van beide spelers geen deelnemer
 * meer, dan verdwijnt de wedstrijd dus meteen: zonder uitslag is er niets
 * meer dat hem in de lijst houdt. Zo wacht de lijst niet op de eerstvolgende
 * deelnemersmutatie om weer te kloppen met de deelnemers.
 *
 * Wissen is idempotent: staat er geen uitslag (meer), dan verandert er niets.
 */
class ClearMatchResult
{
    public function __construct(
        private SyncCompetitionMatches $syncCompetitionMatches,
    ) {}

    /**
     * Zet de wedstrijd terug op openstaand en synchroniseert daarna de lijst.
     *
     * @return bool of de wedstrijd na het wissen nog bestaat
     *
     * @throws ValidationException wanneer de competitie intussen niet meer actief is
     * @throws ModelNotFoundException wanneer de wedstrijd intussen door een sync verwijderd is
     */
    public function handle(CompetitionMatch $match): bool
    {
        return DB::transaction(function () use ($match): bool {
            // Zelfde rijlock en zelfde reden om opnieuw te lezen als in
            // `RecordMatchResult`: `$match` en de statuscheck in
            // `DestroyMatchResultRequest` dateren van vóór de lock.
            $locked = Competition::query()->whereKey($match->competition_id)->lockForUpdate()->firstOrFail();

            $fresh = CompetitionMatch::query()->whereKey($match->id)->firstOrFail();

            if (! $locked->status->allowsResultChanges()) {
                throw ValidationException::withMessages([
                    'score' => __('Results can only be changed while the competition is active.'),
                ]);
            }

            // Er staat geen uitslag (meer), bijvoorbeeld omdat iemand anders
            // ons voor was: er valt niets te wissen en de wedstrijd bestaat
            // nog.
            if (! $fresh->isPlayed()) {
                return true;
            }

            $fresh->fill([
                'first_player_score' => null,
                'second_player_score' => null,
            ]);
            $fresh->status = MatchStatus::Pending;
            $fresh->save();

            // De sync neemt zelf ook de lock op dezelfde competitierij. Dat
            // gebeurt op dezelfde connectie, dus zonder deadlock; zijn eigen
            // transactie wordt hier een savepoint.
            $this->syncCompetitionMatches->handle($locked);

            return CompetitionMatch::query()->whereKey($fresh->id)->exists();
        });
    }
}
