<?php

namespace App\Actions\Competitions;

use App\Models\Competition;
use App\Models\MatchDay;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Wijzigt de datum en tijden van een speeldag (#81).
 *
 * Een andere datum is voor deelnemers een nieuwe datum. Het werk daarvan doen
 * de hooks van `MatchDay`, zodat het bij elk schrijfpad op `date` gebeurt: de
 * `saving`-hook zet een nieuw `availability_requested_at`, de `updated`-hook
 * wist alle beschikbaarheidsrijen van de speeldag en haalt al zijn
 * openstaande wedstrijden uit de planning, ook de vastgezette
 * (`MatchDay::releasePendingMatches()`). `availability_submitted_at` op de
 * koppeltabel blijft onaangeroerd; wie ouder indiende, moet vanzelf opnieuw
 * (`Competition::constrainToAwaitingAvailability()`). Gespeelde wedstrijden
 * schuiven mee; een uitslag is historie en blokkeert het verzetten niet.
 *
 * Deze action levert wat de hooks niet kunnen: de lock, de guard tegen een
 * verouderde bewerkpagina en het aantal vrijgegeven wedstrijden voor de
 * toast. Alles gebeurt onder dezelfde rijlock op de competitie als
 * `ScheduleCompetitionMatches` en `MoveCompetitionMatch`. Zonder die lock kan
 * een gelijktijdige planner-run de verzette dag nog vullen met de oude
 * beschikbaarheid, net nadat die is gewist. De speeldag wordt daarom ná de
 * lock opnieuw gelezen; de instantie uit de route-binding dateert van ervoor.
 *
 * Alleen de begin- of eindtijd wijzigen raakt beschikbaarheid en planning
 * niet. Of de speeldag verzet is, volgt uit `wasChanged('date')` en nooit uit
 * het stempel: binnen dezelfde seconde verandert dat niet. Terugzetten naar
 * de oude datum is opnieuw verzetten: de gewiste antwoorden komen niet terug.
 *
 * Er is bewust geen statusguard: ook op een concept- of afgeronde competitie
 * is een verzette speeldag een nieuwe datum, net als een nieuwe speeldag
 * (besluit Menno bij #74).
 */
class UpdateMatchDay
{
    /**
     * Slaat de datum en tijden op. `$originalDate` is de datum die de
     * beheerder op de bewerkpagina zag. Is de speeldag intussen door een
     * ander verzet, dan zou dit formulier hem stil terugzetten en alle nieuwe
     * antwoorden wissen, ook als de beheerder alleen een tijd aanpaste.
     * Daarom wordt dat geweigerd; omdat de controle binnen de transactie
     * gooit, blijft alles ongewijzigd.
     *
     * @param  array{date: string, starts_at: string, ends_at: string}  $attributes
     * @param  string  $originalDate  `Y-m-d`
     * @return int|null `null` als de datum gelijk bleef, anders het aantal wedstrijden dat uit de planning ging
     *
     * @throws ValidationException als de speeldag sinds het openen van de bewerkpagina verzet is
     */
    public function handle(MatchDay $matchDay, array $attributes, string $originalDate): ?int
    {
        return DB::transaction(function () use ($matchDay, $attributes, $originalDate): ?int {
            Competition::query()->whereKey($matchDay->competition_id)->lockForUpdate()->firstOrFail();

            $fresh = MatchDay::query()->whereKey($matchDay->id)->firstOrFail();

            if ($fresh->date->toDateString() !== $originalDate) {
                throw ValidationException::withMessages([
                    'date' => __('This match day was changed in the meantime. Reload the page and try again.'),
                ]);
            }

            $releasable = $fresh->pendingMatches()->count();

            $fresh->update($attributes);

            return $fresh->wasChanged('date') ? $releasable : null;
        });
    }
}
