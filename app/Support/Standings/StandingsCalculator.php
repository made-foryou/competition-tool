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
 * Het onderling resultaat wordt herhaald, zoals in het ITTF-reglement
 * (besluit Menno #58): scheidt de mini-stand de groep maar gedeeltelijk, dan
 * krijgt elke deelgroep die nog gelijk staat een nieuwe mini-stand, nu alleen
 * over de wedstrijden binnen die deelgroep, enzovoort. In een groep van vier
 * met A>B, B>C, C>A en B>D wordt B eerste (4 mini-punten) en D laatste (0);
 * A en C staan met 2 nog gelijk, en de herhaling over alleen hun onderlinge
 * wedstrijd zet C boven A: B, C, A, D. De herhaling stopt zodra een
 * mini-stand niemand meer scheidt. Een cyclus (A wint van B, B van C, C van
 * A, met gelijk saldo) geeft iedereen dezelfde mini-punten en blijft daarom
 * een gedeelde plaats, net als twee spelers die elkaar nog niet troffen.
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
        $headToHeadKeys = $this->headToHeadKeysPerLevelGroup($tallies, $matches, $pointsSystem);

        $entries = [];

        foreach ($playersById as $playerId => $player) {
            $tally = $tallies[$playerId];

            $entries[] = [
                'player' => $player,
                'tally' => $tally,
                'rankKey' => [$tally['points'], $tally['gamesFor'] - $tally['gamesAgainst'], ...$headToHeadKeys[$playerId]],
                'sortName' => Str::lower(Str::ascii($player->displayName)),
            ];
        }

        usort($entries, fn (array $a, array $b): int => $this->compareRankKeys($b['rankKey'], $a['rankKey'])
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
     * De sleutel uit het onderling resultaat per speler: de spelers worden
     * gegroepeerd op (punten, saldo) en elke groep krijgt haar eigen,
     * herhaalde mini-stand. Een speler die alleen in zijn groep staat, krijgt
     * een lege sleutel.
     *
     * @param  array<int, array{played: int, won: int, lost: int, gamesFor: int, gamesAgainst: int, points: int}>  $tallies
     * @param  list<PlayedMatch>  $matches
     * @return array<int, list<int>>
     */
    private function headToHeadKeysPerLevelGroup(array $tallies, array $matches, PointsSystem $pointsSystem): array
    {
        $levelGroups = [];

        foreach ($tallies as $playerId => $tally) {
            $levelGroups[$tally['points'].':'.($tally['gamesFor'] - $tally['gamesAgainst'])][] = $playerId;
        }

        $keys = [];

        foreach ($levelGroups as $groupIds) {
            $keys += $this->headToHeadKeys($groupIds, $matches, $pointsSystem);
        }

        return $keys;
    }

    /**
     * De opeenvolgende mini-punten per speler binnen één gelijke groep.
     *
     * De eerste waarde zijn de punten uit de wedstrijden waarvan beide spelers
     * in de groep zitten. Spelers met dezelfde mini-punten vormen een
     * deelgroep. Is die deelgroep kleiner dan de groep, dan volgt voor haar
     * een nieuwe mini-stand over alleen de wedstrijden binnen die deelgroep;
     * die punten komen achter de eerste waarde. Scheidt de mini-stand niemand
     * (de deelgroep is de hele groep), dan stopt de herhaling en delen de
     * spelers de plaats.
     *
     * Spelers met dezelfde waarden tot dan toe zitten steeds in dezelfde
     * deelgroep en krijgen dus even lange sleutels.
     *
     * @param  list<int>  $groupIds
     * @param  list<PlayedMatch>  $matches
     * @return array<int, list<int>>
     */
    private function headToHeadKeys(array $groupIds, array $matches, PointsSystem $pointsSystem): array
    {
        if (count($groupIds) < 2) {
            return array_fill_keys($groupIds, []);
        }

        $miniPoints = array_fill_keys($groupIds, 0);
        $matchesWithinGroup = [];

        foreach ($matches as $match) {
            if (! array_key_exists($match->firstPlayerId, $miniPoints) || ! array_key_exists($match->secondPlayerId, $miniPoints)) {
                continue;
            }

            $matchesWithinGroup[] = $match;
            $miniPoints[$match->firstPlayerId] += $pointsSystem->pointsFor($match->firstPlayerScore, $match->secondPlayerScore);
            $miniPoints[$match->secondPlayerId] += $pointsSystem->pointsFor($match->secondPlayerScore, $match->firstPlayerScore);
        }

        $subgroups = [];

        foreach ($miniPoints as $playerId => $points) {
            $subgroups[$points][] = $playerId;
        }

        $keys = [];

        foreach ($subgroups as $points => $subgroupIds) {
            $deeperKeys = count($subgroupIds) < count($groupIds)
                ? $this->headToHeadKeys($subgroupIds, $matchesWithinGroup, $pointsSystem)
                : array_fill_keys($subgroupIds, []);

            foreach ($subgroupIds as $playerId) {
                $keys[$playerId] = [$points, ...$deeperKeys[$playerId]];
            }
        }

        return $keys;
    }

    /**
     * Vergelijkt twee rangsleutels element voor element tot het eerste
     * verschil. PHP's `<=>` op arrays vergelijkt eerst het aantal elementen,
     * en de sleutels zijn door de herhaling van het onderling resultaat niet
     * even lang. Zijn alle gedeelde elementen gelijk, dan beslist de lengte;
     * dat komt niet voor, maar houdt de volgorde deterministisch.
     *
     * @param  list<int>  $first
     * @param  list<int>  $second
     */
    private function compareRankKeys(array $first, array $second): int
    {
        $sharedLength = min(count($first), count($second));

        for ($index = 0; $index < $sharedLength; $index++) {
            if ($first[$index] !== $second[$index]) {
                return $first[$index] <=> $second[$index];
            }
        }

        return count($first) <=> count($second);
    }

    /**
     * Nummert de gesorteerde spelers. Een gelijke rangsleutel (punten, saldo,
     * onderling resultaat) deelt de positie; de volgende positie is de index
     * plus één, zodat de gedeelde plaatsen worden overgeslagen.
     *
     * @param  list<array{
     *     player: StandingsPlayer,
     *     tally: array{played: int, won: int, lost: int, gamesFor: int, gamesAgainst: int, points: int},
     *     rankKey: list<int>,
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
