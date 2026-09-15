<?php

use App\Support\Scheduling\ScheduleBoard;

test('adjacent intervals do not overlap', function () {
    $board = new ScheduleBoard;
    $board->occupyTable(matchDayId: 1, fieldId: 1, startMinute: 0, endMinute: 25);

    expect($board->isTableFree(1, 1, 25, 50))->toBeTrue()
        ->and($board->isTableFree(1, 1, 24, 50))->toBeFalse()
        ->and($board->isTableFree(1, 1, 0, 25))->toBeFalse();
});

test('a player is busy during their own match only', function () {
    $board = new ScheduleBoard;
    $board->occupyPlayer(playerId: 7, matchDayId: 1, startMinute: 25, endMinute: 45);

    expect($board->isPlayerFree(7, 1, 0, 25))->toBeTrue()
        ->and($board->isPlayerFree(7, 1, 20, 40))->toBeFalse()
        ->and($board->isPlayerFree(7, 1, 45, 65))->toBeTrue();
});

test('occupying a player counts towards their matches that day', function () {
    $board = new ScheduleBoard;

    expect($board->matchesOn(7, 1))->toBe(0);

    $board->occupyPlayer(7, 1, 0, 20);
    $board->occupyPlayer(7, 1, 50, 70);

    expect($board->matchesOn(7, 1))->toBe(2)
        ->and($board->matchesOn(8, 1))->toBe(0);
});

test('intervals are returned sorted by start', function () {
    $board = new ScheduleBoard;
    $board->occupyPlayer(7, 1, 50, 70);
    $board->occupyPlayer(7, 1, 0, 20);

    expect($board->intervalsOf(7, 1))->toBe([[0, 20], [50, 70]])
        ->and($board->intervalsOf(7, 2))->toBe([]);
});

test('tables and players are tracked per match day', function () {
    $board = new ScheduleBoard;
    $board->occupyTable(1, 1, 0, 25);
    $board->occupyPlayer(7, 1, 0, 20);

    expect($board->isTableFree(2, 1, 0, 25))->toBeTrue()
        ->and($board->isPlayerFree(7, 2, 0, 20))->toBeTrue()
        ->and($board->matchesOn(7, 2))->toBe(0);
});
