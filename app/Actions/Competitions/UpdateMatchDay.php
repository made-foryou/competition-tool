<?php

namespace App\Actions\Competitions;

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use Illuminate\Support\Facades\DB;

/**
 * Wijzigt de datum en tijden van een speeldag (#81).
 *
 * Een andere datum is voor deelnemers een nieuwe datum. Alle
 * beschikbaarheidsrijen van deze speeldag vervallen, want "ik kan op de 2e"
 * zegt niets over de 9e. `availability_submitted_at` op de koppeltabel blijft
 * onaangeroerd: de `saving`-hook van `MatchDay` zet een nieuw
 * `availability_requested_at`, waardoor iedereen met een oudere inzending
 * vanzelf opnieuw moet indienen (`Competition::constrainToAwaitingAvailability()`).
 * Alle openstaande wedstrijden op de speeldag gaan uit de planning, ook de
 * vastgezette (`MatchDay::releasePendingMatches()`): ze waren ingepland op
 * beschikbaarheid die niet meer bestaat. Gespeelde wedstrijden blijven staan
 * en schuiven mee; een uitslag is historie en blokkeert het verzetten niet.
 *
 * Alleen de begin- of eindtijd wijzigen raakt beschikbaarheid en planning
 * niet. Of de speeldag verzet is, volgt uit `wasChanged('date')` op het model
 * en nooit uit het stempel: binnen dezelfde seconde verandert dat niet.
 * Terugzetten naar de oude datum is opnieuw verzetten: de gewiste antwoorden
 * komen niet terug.
 *
 * Alles gebeurt onder dezelfde rijlock op de competitie als
 * `ScheduleCompetitionMatches` en `MoveCompetitionMatch`. Zonder die lock kan
 * een gelijktijdige planner-run de verzette dag nog vullen met de oude
 * beschikbaarheid, net nadat die is gewist. De speeldag wordt daarom ná de
 * lock opnieuw gelezen; de instantie uit de route-binding dateert van ervoor.
 *
 * Er is bewust geen statusguard: ook op een concept- of afgeronde competitie
 * is een verzette speeldag een nieuwe datum, net als een nieuwe speeldag
 * (besluit Menno bij #74).
 */
class UpdateMatchDay
{
    /**
     * Slaat de datum en tijden op en laat bij een verzette datum de
     * beschikbaarheid en de openstaande planning van de speeldag vervallen.
     *
     * @param  array{date: string, starts_at: string, ends_at: string}  $attributes
     * @return int|null `null` als de datum gelijk bleef, anders het aantal wedstrijden dat uit de planning ging
     */
    public function handle(MatchDay $matchDay, array $attributes): ?int
    {
        return DB::transaction(function () use ($matchDay, $attributes): ?int {
            Competition::query()->whereKey($matchDay->competition_id)->lockForUpdate()->firstOrFail();

            $fresh = MatchDay::query()->whereKey($matchDay->id)->firstOrFail();
            $fresh->update($attributes);

            if (! $fresh->wasChanged('date')) {
                return null;
            }

            MatchDayAvailability::query()->where('match_day_id', $fresh->id)->delete();

            return $fresh->releasePendingMatches();
        });
    }
}
