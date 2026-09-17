<?php

use App\Support\Scheduling\SlotGrid;

require_once __DIR__.'/SchedulingHelpers.php';

/**
 * De begintijden van alle slots als `H:i`.
 *
 * @return list<string>
 */
function slotStartTimes(SlotGrid $grid): array
{
    return array_map(fn ($slot): string => $slot->startsAt(), $grid->slots);
}

test('a match day without a break yields consecutive slots that end before closing time', function () {
    $grid = SlotGrid::for('19:00', '20:30', settings(break: 0));

    expect(slotStartTimes($grid))->toBe(['19:00', '19:25', '19:50'])
        ->and($grid->break)->toBeNull()
        ->and($grid->slots[2]->endsAt())->toBe('20:15');
});

test('a break is inserted after the middle slot and shifts the remaining slots', function () {
    $grid = SlotGrid::for('19:00', '23:00', settings());

    expect(slotStartTimes($grid))->toBe(['19:00', '19:25', '19:50', '20:15', '20:40', '21:20', '21:45', '22:10', '22:35'])
        ->and($grid->breakStartsAt())->toBe('21:05')
        ->and($grid->breakEndsAt())->toBe('21:20')
        ->and($grid->slots[8]->endsAt())->toBe('23:00');
});

test('a break is dropped when fewer than two slots fit', function () {
    $grid = SlotGrid::for('19:00', '19:45', settings());

    expect(slotStartTimes($grid))->toBe(['19:00'])
        ->and($grid->break)->toBeNull()
        ->and($grid->breakStartsAt())->toBeNull();
});

test('a match day too short for one slot yields no slots', function () {
    $grid = SlotGrid::for('19:00', '19:20', settings());

    expect($grid->slots)->toBe([])
        ->and($grid->isEmpty())->toBeTrue()
        ->and($grid->break)->toBeNull();
});

test('slot times are returned as H:i strings', function () {
    $grid = SlotGrid::for('09:00', '10:00', settings(break: 0));

    expect($grid->slots[0]->startsAt())->toBe('09:00')
        ->and($grid->slots[0]->endsAt())->toBe('09:25')
        ->and($grid->slots[1]->startsAt())->toBe('09:25');
});

test('a slot can be looked up by its start time and unknown times give null', function () {
    $grid = SlotGrid::for('19:00', '20:30', settings(break: 0));

    expect($grid->slotAt('19:25')?->index)->toBe(1)
        ->and($grid->slotAt('19:30'))->toBeNull()
        ->and($grid->slotStartingAt(0))->toBeNull();
});

test('the match end excludes the buffer', function () {
    $grid = SlotGrid::for('19:00', '20:30', settings(break: 0));

    expect($grid->matchEndMinute($grid->slots[0]))->toBe(19 * 60 + 20)
        ->and($grid->slots[0]->endMinute)->toBe(19 * 60 + 25);
});
