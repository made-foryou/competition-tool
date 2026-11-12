<?php

namespace App\Support\Standings;

use App\Enums\PointsSystem;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Berekent een klassement uit een set spelers en hun gespeelde wedstrijden.
 * Puur rekenwerk: geen database, geen competitie. De calculator krijgt bewust
 * nooit een `Competition`, zodat een poule-stand later dezelfde berekening
 * kan gebruiken met een deelverzameling van de spelers en wedstrijden.
 *
 * De volgorde (besluit Menno #58):
 *
 * 1. punten, volgens de meegegeven puntentelling;
 * 2. game-saldo (gewonnen min verloren games);
 * 3. onderling resultaat: voor de spelers die na punten én saldo nog gelijk
 *    staan een mini-stand met dezelfde puntentelling, alleen over de
 *    wedstrijden waarin beide spelers tot die groep horen;
 * 4. wie daarna nog gelijk staat, deelt de plaats. Het volgende nummer slaat
 *    de gedeelde plaatsen over (1, 2, 3, 3, 5).
 *
 * Het onderling resultaat is één ronde, zonder recursie: de mini-stand wordt
 * niet opnieuw berekend voor de spelers die daarin nog gelijk staan. Een
 * cyclus (A wint van B, B van C, C van A, met gelijk saldo) laat zich door
 * geen enkele ronde scheiden en blijft een gedeelde plaats. En in een groep
 * van vier met A>B, B>C, C>A en B>D wordt B eerste en D laatste, terwijl A
 * en C de plaats ertussen delen, ook al won C van A: er komt geen tweede
 * ronde over alleen die ene wedstrijd. Zo blijft het één regel die een
 * speler zelf kan narekenen: gelijk na het onderling resultaat is een
 * gedeelde plaats.
 *
 * Binnen een gedeelde plaats staan de spelers op naam (zonder onderscheid in
 * accenten en hoofdletters) en daarna op id. Dat is alleen weergave: de
 * positie blijft gedeeld.
 */
final readonly class StandingsCalculator
{
    /**
     * @param  list<StandingsPlayer>  $players  iedereen die in de stand moet staan, ook zonder wedstrijden
     * @param  list<PlayedMatch>  $matches  de wedstrijden die meetellen
     *
     * @throws InvalidArgumentException bij een dubbele speler-id, of een wedstrijd met een speler die niet in `$players` staat
     */
    public function calculate(array $players, array $matches, PointsSystem $pointsSystem): Standings
    {
        $playersById = $this->indexById($players);
        $tallies = $this->tally($playersById, $matches, $pointsSystem);
        $headToHeadPoints = $this->headToHeadPoints($tallies, $matches, $pointsSystem);

        $entries = [];

        foreach ($playersById as $playerId => $player) {
            $tally = $tallies[$playerId];

            $entries[] = [
                'player' => $player,
                'tally' => $tally,
                'rankKey' => [$tally['points'], $tally['gamesFor'] - $tally['gamesAgainst'], $headToHeadPoints[$playerId]],
                'sortName' => Str::lower(Str::ascii($player->displayName)),
            ];
        }

        usort($entries, fn (array $a, array $b): int => ($b['rankKey'] <=> $a['rankKey'])
            ?: strcmp($a['sortName'], $b['sortName'])
            ?: $a['player']->id <=> $b['player']->id);

        return new Standings(
            rows: $this->rows($entries),
            playedMatchCount: count($matches),
            pointsSystem: $pointsSystem,
        );
    }

    /**
     * @param  list<StandingsPlayer>  $players
     * @return array<int, StandingsPlayer>
     *
     * @throws InvalidArgumentException bij een dubbele speler-id
     */
    private function indexById(array $players): array
    {
        $playersById = [];

        foreach ($players as $player) {
            if (array_key_exists($player->id, $playersById)) {
                throw new InvalidArgumentException("Player {$player->id} appears more than once.");
            }

            $playersById[$player->id] = $player;
        }

        return $playersById;
    }

    /**
     * De telling per speler. Elke wedstrijd telt per kolom: de eerste speler
     * krijgt de eerste score als eigen games, de tweede de tweede.
     *
     * @param  array<int, StandingsPlayer>  $playersById
     * @param  list<PlayedMatch>  $matches
     * @return array<int, array{played: int, won: int, lost: int, gamesFor: int, gamesAgainst: int, points: int}>
     *
     * @throws InvalidArgumentException bij een wedstrijd met een speler die niet in de stand staat
     */
    private function tally(array $playersById, array $matches, PointsSystem $pointsSystem): array
    {
        $tallies = array_map(fn (): array => [
            'played' => 0,
            'won' => 0,
            'lost' => 0,
            'gamesFor' => 0,
            'gamesAgainst' => 0,
            'points' => 0,
        ], $playersById);

        foreach ($matches as $match) {
            foreach ([$match->firstPlayerId, $match->secondPlayerId] as $playerId) {
                if (! array_key_exists($playerId, $tallies)) {
                    throw new InvalidArgumentException("Player {$playerId} has a match but is not in the standings.");
                }
            }

            $first = $tallies[$match->firstPlayerId];
            $second = $tallies[$match->secondPlayerId];

            $tallies[$match->firstPlayerId] = $this->withResult($first, $match->firstPlayerScore, $match->secondPlayerScore, $pointsSystem);
            $tallies[$match->secondPlayerId] = $this->withResult($second, $match->secondPlayerScore, $match->firstPlayerScore, $pointsSystem);
        }

        return $tallies;
    }

    /**
     * @param  array{played: int, won: int, lost: int, gamesFor: int, gamesAgainst: int, points: int}  $tally
     * @return array{played: int, won: int, lost: int, gamesFor: int, gamesAgainst: int, points: int}
     */
    private function withResult(array $tally, int $ownGames, int $opponentGames, PointsSystem $pointsSystem): array
    {
        $isWinner = $ownGames > $opponentGames;

        return [
            'played' => $tally['played'] + 1,
            'won' => $tally['won'] + ($isWinner ? 1 : 0),
            'lost' => $tally['lost'] + ($isWinner ? 0 : 1),
            'gamesFor' => $tally['gamesFor'] + $ownGames,
            'gamesAgainst' => $tally['gamesAgainst'] + $opponentGames,
            'points' => $tally['points'] + $pointsSystem->pointsFor($ownGames, $opponentGames),
        ];
    }

    /**
     * De punten uit de mini-stand: per speler de punten uit de wedstrijden
     * tegen spelers met dezelfde punten en hetzelfde saldo.
     *
     * Een wedstrijd waarvan beide spelers dezelfde (punten, saldo) hebben, is
     * precies een wedstrijd binnen één gelijke groep. Eén doorloop over de
     * wedstrijden dekt daarmee alle groepen tegelijk. Een speler die alleen
     * in zijn groep staat, of die binnen zijn groep niemand trof, houdt 0;
     * dat verandert niets, want de mini-punten worden alleen binnen een groep
     * vergeleken.
     *
     * @param  array<int, array{played: int, won: int, lost: int, gamesFor: int, gamesAgainst: int, points: int}>  $tallies
     * @param  list<PlayedMatch>  $matches
     * @return array<int, int>
     */
    private function headToHeadPoints(array $tallies, array $matches, PointsSystem $pointsSystem): array
    {
        $groupKeys = array_map(
            fn (array $tally): string => $tally['points'].':'.($tally['gamesFor'] - $tally['gamesAgainst']),
            $tallies,
        );

        $points = array_map(fn (): int => 0, $tallies);

        foreach ($matches as $match) {
            if ($groupKeys[$match->firstPlayerId] !== $groupKeys[$match->secondPlayerId]) {
                continue;
            }

            $points[$match->firstPlayerId] += $pointsSystem->pointsFor($match->firstPlayerScore, $match->secondPlayerScore);
            $points[$match->secondPlayerId] += $pointsSystem->pointsFor($match->secondPlayerScore, $match->firstPlayerScore);
        }

        return $points;
    }

    /**
     * Nummert de gesorteerde spelers. Een gelijke (punten, saldo, onderling)
     * deelt de positie; de volgende positie is de index plus één, zodat de
     * gedeelde plaatsen worden overgeslagen.
     *
     * @param  list<array{
     *     player: StandingsPlayer,
     *     tally: array{played: int, won: int, lost: int, gamesFor: int, gamesAgainst: int, points: int},
     *     rankKey: array{int, int, int},
     *     sortName: string,
     * }>  $entries
     * @return list<StandingRow>
     */
    private function rows(array $entries): array
    {
        $positions = [];
        $position = 0;
        $previousRankKey = null;

        foreach ($entries as $index => $entry) {
            if ($entry['rankKey'] !== $previousRankKey) {
                $position = $index + 1;
                $previousRankKey = $entry['rankKey'];
            }

            $positions[] = $position;
        }

        $playersPerPosition = array_count_values($positions);

        $rows = [];

        foreach ($entries as $index => $entry) {
            $rows[] = new StandingRow(
                position: $positions[$index],
                sharesPosition: $playersPerPosition[$positions[$index]] > 1,
                player: $entry['player'],
                played: $entry['tally']['played'],
                won: $entry['tally']['won'],
                lost: $entry['tally']['lost'],
                gamesFor: $entry['tally']['gamesFor'],
                gamesAgainst: $entry['tally']['gamesAgainst'],
                points: $entry['tally']['points'],
            );
        }

        return $rows;
    }
}
