<?php

use App\Enums\PlacementViolation;
use App\Support\Scheduling\Placement;
use App\Support\Scheduling\PlacementValidator;
use App\Support\Scheduling\ScheduleBoard;
use App\Support\Scheduling\SchedulingContext;
use App\Support\Scheduling\Slot;

require_once __DIR__.'/SchedulingHelpers.php';

/**
 * Eén speeldag van 19:00 tot 21:00 met twee tafels, waarop de spelers 1 tot
 * en met 4 beschikbaar zijn.
 */
function validatorContext(int $max = 0, int $rest = 10, ?ScheduleBoard $board = null): SchedulingContext
{
    return schedulingContext(
        days: [5 => ['starts_at' => '19:00', 'ends_at' => '21:00', 'fields' => [1, 2]]],
        availability: [5 => [1, 2, 3, 4]],
        settings: settings(rest: $rest, break: 0, max: $max),
        board: $board,
    );
}

test('a field that is not on the match day has a violation', function () {
    $context = validatorContext();
    $slot = $context->matchDays[0]->grid->slots[0];

    expect((new PlacementValidator)->violation(new Placement(5, 9, $slot), 1, 2, $context))
        ->toBe(PlacementViolation::FieldNotOnMatchDay)
        ->and((new PlacementValidator)->violation(new Placement(99, 1, $slot), 1, 2, $context))
        ->toBe(PlacementViolation::FieldNotOnMatchDay);
});

test('a slot outside the grid has a violation', function () {
    $context = validatorContext();

    expect((new PlacementValidator)->violation(new Placement(5, 1, new Slot(0, 18 * 60, 18 * 60 + 25)), 1, 2, $context))
        ->toBe(PlacementViolation::OutsideOpeningHours);
});

test('an unavailable player has a violation', function () {
    $context = validatorContext();
    $slot = $context->matchDays[0]->grid->slots[0];

    expect((new PlacementValidator)->violation(new Placement(5, 1, $slot), 1, 99, $context))
        ->toBe(PlacementViolation::PlayerUnavailable);
});

test('a player at the daily maximum has a violation', function () {
    $board = new ScheduleBoard;
    $board->occupyPlayer(1, 5, 19 * 60, 19 * 60 + 20);
    $context = validatorContext(max: 1, board: $board);
    $slot = $context->matchDays[0]->grid->slots[2];

    expect((new PlacementValidator)->violation(new Placement(5, 1, $slot), 1, 2, $context))
        ->toBe(PlacementViolation::MaxMatchesReached);
});

test('a player who already plays at that time has a violation', function () {
    $board = new ScheduleBoard;
    $board->occupyPlayer(1, 5, 19 * 60, 19 * 60 + 20);
    $context = validatorContext(board: $board);
    $slot = $context->matchDays[0]->grid->slots[0];

    expect((new PlacementValidator)->violation(new Placement(5, 2, $slot), 1, 2, $context))
        ->toBe(PlacementViolation::PlayerBusy);
});

test('an occupied table has a violation', function () {
    $board = new ScheduleBoard;
    $board->occupyTable(5, 1, 19 * 60, 19 * 60 + 25);
    $context = validatorContext(board: $board);
    $slot = $context->matchDays[0]->grid->slots[0];

    expect((new PlacementValidator)->violation(new Placement(5, 1, $slot), 3, 4, $context))
        ->toBe(PlacementViolation::TableOccupied);
});

test('a valid placement has no violation', function () {
    $context = validatorContext();
    $slot = $context->matchDays[0]->grid->slots[0];

    expect((new PlacementValidator)->violation(new Placement(5, 1, $slot), 1, 2, $context))->toBeNull();
});

test('a maximum of zero means unlimited', function () {
    $board = new ScheduleBoard;
    $board->occupyPlayer(1, 5, 19 * 60, 19 * 60 + 20);
    $board->occupyPlayer(1, 5, 19 * 60 + 25, 19 * 60 + 45);
    $context = validatorContext(max: 0, board: $board);
    $slot = $context->matchDays[0]->grid->slots[2];

    expect((new PlacementValidator)->violation(new Placement(5, 1, $slot), 1, 2, $context))->toBeNull();
});

test('too little rest is not a violation', function () {
    $board = new ScheduleBoard;
    $board->occupyPlayer(1, 5, 19 * 60, 19 * 60 + 20);
    $context = validatorContext(rest: 60, board: $board);
    $slot = $context->matchDays[0]->grid->slots[1];

    expect((new PlacementValidator)->violation(new Placement(5, 1, $slot), 1, 2, $context))->toBeNull();
});
