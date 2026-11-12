<?php

use App\Enums\PointsSystem;

test('points are awarded per points system', function (PointsSystem $system, int $ownGames, int $opponentGames, int $expected) {
    expect($system->pointsFor($ownGames, $opponentGames))->toBe($expected);
})->with([
    'win 2 loss 0, win' => [PointsSystem::WinTwoLossZero, 3, 1, 2],
    'win 2 loss 0, loss' => [PointsSystem::WinTwoLossZero, 1, 3, 0],
    'win 2 loss 1, win' => [PointsSystem::WinTwoLossOne, 3, 0, 2],
    'win 2 loss 1, loss' => [PointsSystem::WinTwoLossOne, 0, 3, 1],
    'games won, win' => [PointsSystem::GamesWon, 3, 1, 3],
    'games won, loss' => [PointsSystem::GamesWon, 2, 3, 2],
]);
