<?php

namespace App\Actions\Competitions;

use App\Enums\CompetitionType;
use App\Enums\MatchStatus;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use Illuminate\Support\Facades\DB;

/**
 * Synchroniseert de wedstrijdenlijst van een competitie met de actuele
 * deelnemerslijst. Wordt aangeroepen na elke deelnemersmutatie, zodat de
 * lijst automatisch meebeweegt zonder aparte genereer-knop.
 *
 * Regels: pending-wedstrijden waarvan het paar niet meer gewenst is worden
 * verwijderd; gespeelde wedstrijden blijven altijd staan (historisch
 * resultaat, ook als een speler zich afmeldt); voor een paar dat al bestaat
 * (pending of gespeeld) komt nooit een duplicaat bij.
 */
class SyncCompetitionMatches
{
    public function handle(Competition $competition): void
    {
        DB::transaction(function () use ($competition): void {
            /** @var list<int> $participantIds */
            $participantIds = $competition->participants()
                ->orderBy('users.id')
                ->pluck('users.id')
                ->all();

            $desiredPairs = $this->desiredPairs($competition, $participantIds);

            $desiredKeys = [];

            foreach ($desiredPairs as [$firstPlayerId, $secondPlayerId]) {
                $desiredKeys[$firstPlayerId.'-'.$secondPlayerId] = true;
            }

            $existingMatches = CompetitionMatch::query()
                ->where('competition_id', $competition->id)
                ->get(['id', 'first_player_id', 'second_player_id', 'status']);

            $staleMatchIds = [];
            $existingKeys = [];

            foreach ($existingMatches as $match) {
                $pairKey = $match->first_player_id.'-'.$match->second_player_id;
                $existingKeys[$pairKey] = true;

                if (! isset($desiredKeys[$pairKey]) && ! $match->isPlayed()) {
                    $staleMatchIds[] = $match->id;
                }
            }

            if ($staleMatchIds !== []) {
                CompetitionMatch::query()->whereIn('id', $staleMatchIds)->delete();
            }

            $missing = array_values(array_filter(
                $desiredPairs,
                fn (array $pair): bool => ! isset($existingKeys[$pair[0].'-'.$pair[1]]),
            ));

            if ($missing !== []) {
                CompetitionMatch::query()->insert($this->rowsFor($competition, $missing));
            }
        });
    }

    /**
     * De gewenste paren voor het competitietype. Toekomstige typen (en
     * poule-indeling via CompetitionSettings) krijgen hier hun eigen
     * strategie.
     *
     * @param  list<int>  $participantIds  oplopend gesorteerd op id
     * @return list<array{int, int}>
     */
    protected function desiredPairs(Competition $competition, array $participantIds): array
    {
        return match ($competition->type) {
            CompetitionType::TheoSchilthuizenBokaal => $this->roundRobinPairs($participantIds),
        };
    }

    /**
     * Enkelvoudige round-robin: elk uniek paar precies één keer. Doordat de
     * ids oplopend gesorteerd binnenkomen, is elk paar meteen canoniek
     * (laagste id als first player) en de volgorde deterministisch.
     *
     * @param  list<int>  $participantIds  oplopend gesorteerd op id
     * @return list<array{int, int}>
     */
    protected function roundRobinPairs(array $participantIds): array
    {
        $pairs = [];
        $count = count($participantIds);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $pairs[] = [$participantIds[$i], $participantIds[$j]];
            }
        }

        return $pairs;
    }

    /**
     * Insert-rijen voor de ontbrekende paren. `insert()` vult geen
     * timestamps, dus die zetten we zelf.
     *
     * @param  list<array{int, int}>  $pairs
     * @return list<array<string, mixed>>
     */
    private function rowsFor(Competition $competition, array $pairs): array
    {
        $now = now();

        return array_map(
            fn (array $pair): array => [
                'competition_id' => $competition->id,
                'first_player_id' => $pair[0],
                'second_player_id' => $pair[1],
                'status' => MatchStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $pairs,
        );
    }
}
