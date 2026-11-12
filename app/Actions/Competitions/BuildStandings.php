<?php

namespace App\Actions\Competitions;

use App\Enums\MatchStatus;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\User;
use App\Support\Standings\PlayedMatch;
use App\Support\Standings\Standings;
use App\Support\Standings\StandingsCalculator;
use App\Support\Standings\StandingsPlayer;

/**
 * Leest de database uit en levert het klassement van een competitie. Eén
 * berekening voor de beheerkant, de deelnemerskant en later een publieke
 * stand; het rekenwerk zelf zit in `StandingsCalculator`.
 *
 * - **Live, nooit opgeslagen.** Een opgeslagen stand zou een tweede waarheid
 *   zijn die bij elke correctie van een uitslag mee moet.
 * - **Alleen `Played` met beide scores telt.** Een gespeelde wedstrijd zonder
 *   (volledige) score bestaat in testdata en telt niet mee. Er wordt per kolom
 *   gerekend: de canonieke volgorde (laagste id eerst) geldt alleen voor
 *   schrijvers via het model, en de sync, de planner en testhelpers schrijven
 *   buiten de `saving`-hook om.
 * - **Wie in de stand staat:** alle huidige deelnemers (de pivot, ongeacht
 *   rol: een gekoppelde beheerder is gewoon speler), ook wie nog niets
 *   gespeeld heeft, plus iedereen met een meetellende wedstrijd in deze
 *   competitie. Een niet-gekoppelde beheerder zonder wedstrijden staat er niet
 *   in.
 * - **Een vertrokken deelnemer blijft staan** met `isParticipant = false`.
 *   Zijn wedstrijden tellen gewoon mee, ook voor zijn tegenstanders.
 * - **De puntentelling** komt uit de competitie-instelling
 *   (`CompetitionSettings::$pointsSystem`), die na de eerste uitslag vastligt.
 */
class BuildStandings
{
    public function __construct(
        private StandingsCalculator $standingsCalculator,
    ) {}

    public function handle(Competition $competition): Standings
    {
        $participantIds = array_values(array_map(
            intval(...),
            $competition->participants()->pluck('users.id')->all(),
        ));

        $matches = array_values($competition->matches()
            ->where('status', MatchStatus::Played->value)
            ->whereNotNull('first_player_score')
            ->whereNotNull('second_player_score')
            ->get(['first_player_id', 'second_player_id', 'first_player_score', 'second_player_score'])
            ->map(fn (CompetitionMatch $match): PlayedMatch => new PlayedMatch(
                firstPlayerId: (int) $match->first_player_id,
                secondPlayerId: (int) $match->second_player_id,
                firstPlayerScore: (int) $match->first_player_score,
                secondPlayerScore: (int) $match->second_player_score,
            ))
            ->all());

        $playerIds = array_values(array_unique([
            ...$participantIds,
            ...array_map(fn (PlayedMatch $match): int => $match->firstPlayerId, $matches),
            ...array_map(fn (PlayedMatch $match): int => $match->secondPlayerId, $matches),
        ]));

        $players = array_values(User::query()
            ->whereKey($playerIds)
            ->get(['id', 'name', 'nickname'])
            ->map(fn (User $user): StandingsPlayer => new StandingsPlayer(
                id: $user->id,
                displayName: $user->display_name,
                isParticipant: in_array($user->id, $participantIds, true),
            ))
            ->all());

        return $this->standingsCalculator->calculate($players, $matches, $competition->settings->pointsSystem);
    }
}
