<?php

use App\Enums\PointsSystem;
use App\Support\Standings\PlayedMatch;
use App\Support\Standings\StandingRow;
use App\Support\Standings\Standings;
use App\Support\Standings\StandingsCalculator;
use App\Support\Standings\StandingsPlayer;

/**
 * Een speler voor de calculator. Eigen naam: Pest deelt één globale
 * functienaamruimte over alle testbestanden.
 */
function standingsTestPlayer(int $id, string $name, bool $isParticipant = true): StandingsPlayer
{
    return new StandingsPlayer($id, $name, $isParticipant);
}

/**
 * Een gespeelde wedstrijd; elke score hoort bij de speler in dezelfde kolom.
 */
function standingsTestMatch(int $firstPlayerId, int $secondPlayerId, int $firstPlayerScore, int $secondPlayerScore): PlayedMatch
{
    return new PlayedMatch($firstPlayerId, $secondPlayerId, $firstPlayerScore, $secondPlayerScore);
}

/**
 * De stand als "positie:naam" per regel, in volgorde.
 *
 * @return list<string>
 */
function standingsTestOrder(Standings $standings): array
{
    return array_map(fn (StandingRow $row): string => $row->position.':'.$row->player->displayName, $standings->rows);
}

/**
 * @param  list<StandingsPlayer>  $players
 * @param  list<PlayedMatch>  $matches
 */
function standingsTestCalculate(array $players, array $matches, PointsSystem $pointsSystem = PointsSystem::WinTwoLossZero): Standings
{
    return (new StandingsCalculator)->calculate($players, $matches, $pointsSystem);
}

test('a match counts the same whichever column a player is stored in', function (PlayedMatch $match) {
    $standings = standingsTestCalculate([standingsTestPlayer(1, 'Anna'), standingsTestPlayer(2, 'Bert')], [$match]);

    $winner = $standings->rowFor(1);
    $loser = $standings->rowFor(2);

    expect([$winner->played, $winner->won, $winner->lost, $winner->gamesFor, $winner->gamesAgainst, $winner->points])
        ->toBe([1, 1, 0, 3, 1, 2]);
    expect([$loser->played, $loser->won, $loser->lost, $loser->gamesFor, $loser->gamesAgainst, $loser->points])
        ->toBe([1, 0, 1, 1, 3, 0]);
})->with([
    'winner stored first' => fn () => standingsTestMatch(1, 2, 3, 1),
    'winner stored second' => fn () => standingsTestMatch(2, 1, 1, 3),
]);

test('points follow the points system', function (PointsSystem $pointsSystem, int $winnerPoints, int $loserPoints) {
    $standings = standingsTestCalculate(
        [standingsTestPlayer(1, 'Anna'), standingsTestPlayer(2, 'Bert')],
        [standingsTestMatch(1, 2, 3, 1)],
        $pointsSystem,
    );

    expect($standings->rowFor(1)->points)->toBe($winnerPoints)
        ->and($standings->rowFor(2)->points)->toBe($loserPoints)
        ->and($standings->pointsSystem)->toBe($pointsSystem);
})->with([
    'win 2 loss 0' => [PointsSystem::WinTwoLossZero, 2, 0],
    'win 2 loss 1' => [PointsSystem::WinTwoLossOne, 2, 1],
    'games won, also in a loss' => [PointsSystem::GamesWon, 3, 1],
]);

test('games for, games against and the game difference add up over all matches', function () {
    $standings = standingsTestCalculate(
        [standingsTestPlayer(1, 'Anna'), standingsTestPlayer(2, 'Bert'), standingsTestPlayer(3, 'Carla')],
        [standingsTestMatch(1, 2, 3, 1), standingsTestMatch(1, 3, 2, 3)],
    );

    $row = $standings->rowFor(1);

    expect([$row->played, $row->won, $row->lost, $row->gamesFor, $row->gamesAgainst, $row->gameDifference()])
        ->toBe([2, 1, 1, 5, 4, 1]);
});

test('the number of counted matches is reported', function () {
    $standings = standingsTestCalculate(
        [standingsTestPlayer(1, 'Anna'), standingsTestPlayer(2, 'Bert'), standingsTestPlayer(3, 'Carla')],
        [standingsTestMatch(1, 2, 3, 1), standingsTestMatch(1, 3, 2, 3)],
    );

    expect($standings->playedMatchCount)->toBe(2)
        ->and($standings->hasResults())->toBeTrue();
});

/**
 * Wim heeft de meeste punten maar een kleiner saldo dan Anna; Kees en Bert
 * hebben evenveel punten en verschillen alleen in saldo.
 */
test('points rank first and the game difference breaks a tie on points', function () {
    $standings = standingsTestCalculate(
        [standingsTestPlayer(1, 'Anna'), standingsTestPlayer(2, 'Kees'), standingsTestPlayer(3, 'Bert'), standingsTestPlayer(4, 'Wim')],
        [
            standingsTestMatch(4, 3, 3, 2),
            standingsTestMatch(4, 2, 3, 2),
            standingsTestMatch(1, 3, 3, 0),
        ],
    );

    expect(standingsTestOrder($standings))->toBe(['1:Wim', '2:Anna', '3:Kees', '4:Bert']);
});

/**
 * Zeger en Anna hebben allebei 2 punten en saldo 0; Zeger won hun onderlinge
 * wedstrijd.
 */
test('the head-to-head result separates two players level on points and game difference', function () {
    $standings = standingsTestCalculate(
        [standingsTestPlayer(1, 'Anna'), standingsTestPlayer(2, 'Zeger'), standingsTestPlayer(3, 'Carla'), standingsTestPlayer(4, 'Dirk')],
        [
            standingsTestMatch(2, 1, 3, 1),
            standingsTestMatch(3, 2, 3, 1),
            standingsTestMatch(1, 4, 3, 1),
        ],
    );

    expect(standingsTestOrder($standings))->toBe(['1:Carla', '2:Zeger', '3:Anna', '4:Dirk']);
});

/**
 * Wim, Kees en Anna hebben elk 4 punten en saldo 0. Onderling won Wim van
 * beiden en Kees van Anna.
 */
test('a head-to-head standing of three can separate all of them', function () {
    $standings = standingsTestCalculate(
        [
            standingsTestPlayer(1, 'Anna'),
            standingsTestPlayer(2, 'Kees'),
            standingsTestPlayer(3, 'Wim'),
            standingsTestPlayer(4, 'Yara'),
            standingsTestPlayer(5, 'Xander'),
        ],
        [
            standingsTestMatch(3, 2, 3, 0),
            standingsTestMatch(3, 1, 3, 0),
            standingsTestMatch(5, 3, 3, 0),
            standingsTestMatch(4, 3, 3, 0),
            standingsTestMatch(2, 1, 3, 0),
            standingsTestMatch(2, 5, 3, 0),
            standingsTestMatch(4, 2, 3, 0),
            standingsTestMatch(1, 5, 3, 0),
            standingsTestMatch(1, 4, 3, 0),
        ],
    );

    expect(standingsTestOrder($standings))->toBe(['1:Yara', '2:Wim', '3:Kees', '4:Anna', '5:Xander']);
});

/**
 * Kees, Wim en Anna staan gelijk op punten en saldo; binnen die groep is
 * alleen Kees–Wim gespeeld (Kees won).
 *
 * Bij win 2 – verlies 0 levert die nederlaag Wim niets op en deelt hij de
 * plaats met Anna, die niemand uit de groep trof. Bij win 2 – verlies 1 is
 * dezelfde nederlaag in de mini-stand 1 punt waard tegen 0 voor Anna, dus
 * staat Wim boven haar. De buitenstaanders schuiven per telling mee.
 */
test('only matches within the level group count head-to-head', function (PointsSystem $pointsSystem, array $expectedOrder) {
    $standings = standingsTestCalculate(
        [
            standingsTestPlayer(1, 'Anna'),
            standingsTestPlayer(2, 'Kees'),
            standingsTestPlayer(3, 'Wim'),
            standingsTestPlayer(4, 'Xander'),
            standingsTestPlayer(5, 'Yara'),
        ],
        [
            standingsTestMatch(2, 3, 3, 0),
            standingsTestMatch(4, 2, 3, 0),
            standingsTestMatch(3, 4, 3, 0),
            standingsTestMatch(1, 4, 3, 0),
            standingsTestMatch(5, 1, 3, 0),
        ],
        $pointsSystem,
    );

    expect(standingsTestOrder($standings))->toBe($expectedOrder);
})->with([
    'win 2 loss 0: the loser and the player who did not play share' => [
        PointsSystem::WinTwoLossZero,
        ['1:Yara', '2:Kees', '3:Anna', '3:Wim', '5:Xander'],
    ],
    'win 2 loss 1: a lost head-to-head match still earns a point' => [
        PointsSystem::WinTwoLossOne,
        ['1:Xander', '2:Kees', '3:Wim', '4:Anna', '5:Yara'],
    ],
]);

/**
 * Anna, Bert en Carla winnen elk één keer van elkaar met dezelfde score.
 */
test('a head-to-head cycle stays a shared place', function () {
    $standings = standingsTestCalculate(
        [standingsTestPlayer(1, 'Carla'), standingsTestPlayer(2, 'Bert'), standingsTestPlayer(3, 'Anna')],
        [
            standingsTestMatch(3, 1, 3, 1),
            standingsTestMatch(1, 2, 3, 1),
            standingsTestMatch(2, 3, 3, 1),
        ],
    );

    expect(standingsTestOrder($standings))->toBe(['1:Anna', '1:Bert', '1:Carla']);
});

/**
 * Anna, Bert, Carla en Dirk hebben elk 4 punten en saldo +3. Onderling:
 * Anna>Bert, Bert>Carla, Carla>Anna en Bert>Dirk, dus mini-punten Bert 4,
 * Anna 2, Carla 2, Dirk 0. Anna en Carla staan dan nog gelijk; de herhaling
 * over alleen hun onderlinge wedstrijd (Carla won) zet Carla boven Anna.
 */
test('the head-to-head standing is repeated for players who are still level', function () {
    $standings = standingsTestCalculate(
        [
            standingsTestPlayer(1, 'Anna'),
            standingsTestPlayer(2, 'Bert'),
            standingsTestPlayer(3, 'Carla'),
            standingsTestPlayer(4, 'Dirk'),
            standingsTestPlayer(5, 'Xander'),
            standingsTestPlayer(6, 'Yara'),
        ],
        [
            standingsTestMatch(1, 2, 3, 0),
            standingsTestMatch(2, 3, 3, 0),
            standingsTestMatch(3, 1, 3, 0),
            standingsTestMatch(2, 4, 3, 0),
            standingsTestMatch(1, 5, 3, 0),
            standingsTestMatch(3, 5, 3, 0),
            standingsTestMatch(4, 5, 3, 0),
            standingsTestMatch(4, 6, 3, 0),
        ],
    );

    expect(array_map(fn (StandingRow $row): array => [$row->position, $row->player->displayName, $row->sharesPosition], $standings->rows))
        ->toBe([
            [1, 'Bert', false],
            [2, 'Carla', false],
            [3, 'Anna', false],
            [4, 'Dirk', false],
            [5, 'Yara', false],
            [6, 'Xander', false],
        ]);
});

/**
 * Anna, Bram, Dirk, Kees en Wim hebben elk 4 punten en saldo +2; Yara (−4)
 * en Xander (−6) staan los onderaan.
 *
 * 1. Mini-stand over de vijf (Wim>Kees, Kees>Anna, Anna>Dirk, Bram>Dirk,
 *    Bram>Wim): Bram 4, Anna 2, Kees 2, Wim 2, Dirk 0.
 * 2. Herhaling over Anna, Kees en Wim (Wim>Kees, Kees>Anna): Kees 2, Wim 2,
 *    Anna 0.
 * 3. Herhaling over Kees en Wim (Wim>Kees): Wim 2, Kees 0.
 *
 * Uitkomst: Bram, Wim, Kees, Anna, Dirk, allemaal een eigen plaats.
 */
test('the head-to-head standing is repeated until no player is separated any more', function () {
    $standings = standingsTestCalculate(
        [
            standingsTestPlayer(1, 'Anna'),
            standingsTestPlayer(2, 'Bram'),
            standingsTestPlayer(3, 'Dirk'),
            standingsTestPlayer(4, 'Kees'),
            standingsTestPlayer(5, 'Wim'),
            standingsTestPlayer(6, 'Xander'),
            standingsTestPlayer(7, 'Yara'),
        ],
        [
            standingsTestMatch(5, 4, 3, 2),
            standingsTestMatch(4, 1, 3, 2),
            standingsTestMatch(1, 3, 3, 2),
            standingsTestMatch(2, 3, 3, 2),
            standingsTestMatch(2, 5, 3, 2),
            standingsTestMatch(5, 6, 3, 1),
            standingsTestMatch(4, 7, 3, 1),
            standingsTestMatch(1, 6, 3, 1),
            standingsTestMatch(3, 6, 3, 1),
            standingsTestMatch(3, 7, 3, 1),
        ],
    );

    expect(standingsTestOrder($standings))->toBe(['1:Bram', '2:Wim', '3:Kees', '4:Anna', '5:Dirk', '6:Yara', '7:Xander']);
});

/**
 * Wim, Kees en Anna hebben elk 4 punten en saldo +2; Bert heeft ook 4 punten
 * maar saldo 0 en hoort dus niet bij hun groep. Onderling: Wim>Kees en
 * Kees>Anna, dus Wim 2, Kees 2, Anna 0, en de herhaling over Wim>Kees zet Wim
 * boven Kees. Kees' winst op Bert telt niet mee; zou die meetellen, dan had
 * Kees 4 mini-punten en stond hij boven Wim. Bert staat op zijn saldo onder
 * de groep.
 */
test('a player level on points but not on game difference stays out of the head-to-head standing', function () {
    $standings = standingsTestCalculate(
        [
            standingsTestPlayer(1, 'Anna'),
            standingsTestPlayer(2, 'Bert'),
            standingsTestPlayer(3, 'Kees'),
            standingsTestPlayer(4, 'Wim'),
            standingsTestPlayer(5, 'Xander'),
            standingsTestPlayer(6, 'Yara'),
        ],
        [
            standingsTestMatch(4, 3, 3, 2),
            standingsTestMatch(3, 1, 3, 2),
            standingsTestMatch(3, 2, 3, 1),
            standingsTestMatch(4, 5, 3, 2),
            standingsTestMatch(1, 5, 3, 1),
            standingsTestMatch(1, 6, 3, 2),
            standingsTestMatch(2, 5, 3, 2),
            standingsTestMatch(2, 6, 3, 2),
        ],
    );

    expect(standingsTestOrder($standings))->toBe(['1:Wim', '2:Kees', '3:Anna', '4:Bert', '5:Yara', '6:Xander']);
});

test('fully level players share a place and the next position skips it', function () {
    $standings = standingsTestCalculate(
        [standingsTestPlayer(1, 'Anna'), standingsTestPlayer(2, 'Bert'), standingsTestPlayer(3, 'Carla'), standingsTestPlayer(4, 'Dirk')],
        [
            standingsTestMatch(1, 2, 3, 0),
            standingsTestMatch(1, 3, 3, 0),
            standingsTestMatch(2, 4, 3, 0),
            standingsTestMatch(3, 4, 3, 0),
        ],
    );

    expect(array_map(fn (StandingRow $row): array => [$row->position, $row->sharesPosition], $standings->rows))
        ->toBe([[1, false], [2, true], [2, true], [4, false]]);
});

test('players sharing a place are listed by name ignoring accents and case, then by id', function () {
    $standings = standingsTestCalculate([
        standingsTestPlayer(5, 'Anna'),
        standingsTestPlayer(1, 'Zoë'),
        standingsTestPlayer(4, 'anna'),
        standingsTestPlayer(2, 'émile'),
        standingsTestPlayer(3, 'Bert'),
    ], []);

    expect(standingsTestOrder($standings))->toBe(['1:anna', '1:Anna', '1:Bert', '1:émile', '1:Zoë']);
});

/**
 * Bij win 2 – verlies 0 en bij games won staat iemand die nog niet speelde
 * (saldo 0) boven iemand die alles met 0–3 verloor (saldo −3): ze hebben
 * evenveel punten en het saldo beslist. Bij win 2 – verlies 1 levert het
 * verlies een punt op en staat de verliezer erboven.
 */
test('a player without matches ranks above a player who lost everything unless a loss earns points', function (PointsSystem $pointsSystem, array $expectedOrder) {
    $standings = standingsTestCalculate(
        [standingsTestPlayer(1, 'Anna'), standingsTestPlayer(2, 'Bert'), standingsTestPlayer(3, 'Carla')],
        [standingsTestMatch(3, 1, 3, 0)],
        $pointsSystem,
    );

    expect(standingsTestOrder($standings))->toBe($expectedOrder);
})->with([
    'win 2 loss 0' => [PointsSystem::WinTwoLossZero, ['1:Carla', '2:Bert', '3:Anna']],
    'games won' => [PointsSystem::GamesWon, ['1:Carla', '2:Bert', '3:Anna']],
    'win 2 loss 1' => [PointsSystem::WinTwoLossOne, ['1:Carla', '2:Anna', '3:Bert']],
]);

test('players without matches all share the first place and there are no results', function () {
    $standings = standingsTestCalculate([standingsTestPlayer(1, 'Anna'), standingsTestPlayer(2, 'Bert')], []);

    expect(array_map(fn (StandingRow $row): array => [$row->position, $row->sharesPosition, $row->played, $row->points], $standings->rows))
        ->toBe([[1, true, 0, 0], [1, true, 0, 0]]);
    expect($standings->hasResults())->toBeFalse()
        ->and($standings->playedMatchCount)->toBe(0);
});

test('a row is looked up by player id', function () {
    $standings = standingsTestCalculate([standingsTestPlayer(7, 'Anna', isParticipant: false)], []);

    expect($standings->rowFor(7)?->player)->toEqual(standingsTestPlayer(7, 'Anna', isParticipant: false))
        ->and($standings->rowFor(8))->toBeNull();
});

test('a match with a player outside the standings is rejected', function () {
    standingsTestCalculate([standingsTestPlayer(1, 'Anna')], [standingsTestMatch(1, 2, 3, 1)]);
})->throws(InvalidArgumentException::class, 'Player 2 has a match but is not in the standings.');

test('a player listed twice is rejected', function () {
    standingsTestCalculate([standingsTestPlayer(1, 'Anna'), standingsTestPlayer(1, 'Anna')], []);
})->throws(InvalidArgumentException::class, 'Player 1 appears more than once.');

test('an impossible played match is rejected', function (int $secondPlayerId, int $firstPlayerScore, int $secondPlayerScore, string $message) {
    expect(fn () => standingsTestMatch(1, $secondPlayerId, $firstPlayerScore, $secondPlayerScore))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'the same player on both sides' => [1, 3, 1, 'A match cannot pair a player against themselves.'],
    'a negative score' => [2, 3, -1, 'A score cannot be negative.'],
    'a level score' => [2, 2, 2, 'A played match has exactly one winner.'],
]);
