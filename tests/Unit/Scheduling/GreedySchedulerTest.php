<?php

use App\Enums\SchedulingFailure;
use App\Support\Scheduling\ClockTime;
use App\Support\Scheduling\GreedyScheduler;
use App\Support\Scheduling\PendingMatch;
use App\Support\Scheduling\Placement;
use App\Support\Scheduling\ScheduleBoard;
use App\Support\Scheduling\SchedulingSolution;

require_once __DIR__.'/SchedulingHelpers.php';

/**
 * Een plaatsing als korte tekst, om twee runs met elkaar te vergelijken.
 */
function placementKey(Placement $placement): string
{
    return $placement->matchDayId.'/'.$placement->fieldId.'/'.$placement->slot->index;
}

/**
 * Alle plaatsingen als wedstrijd-id => tekst, op id gesorteerd.
 *
 * @return array<int, string>
 */
function placementKeys(SchedulingSolution $solution): array
{
    $keys = array_map(placementKey(...), $solution->placements());

    ksort($keys);

    return $keys;
}

/**
 * Controleert dat geen enkele speler op hetzelfde moment twee wedstrijden
 * heeft.
 *
 * @param  list<PendingMatch>  $matches
 */
function expectNoPlayerClash(SchedulingSolution $solution, array $matches): void
{
    $seen = [];

    foreach ($matches as $match) {
        $placement = $solution->placements()[$match->id] ?? null;

        if ($placement === null) {
            continue;
        }

        foreach ([$match->firstPlayerId, $match->secondPlayerId] as $playerId) {
            $key = $playerId.'@'.$placement->matchDayId.'/'.$placement->slot->startMinute;

            expect($seen)->not->toHaveKey($key);

            $seen[$key] = true;
        }
    }
}

/**
 * Drie spelers die elkaar allemaal treffen op één avond met twee tafels: de
 * enige manier om alles te plaatsen is op verschillende momenten.
 */
test('a player is never scheduled on two fields at the same time', function () {
    $context = schedulingContext([1 => ['fields' => [1, 2]]], [1 => [1, 2, 3]], settings(break: 0));
    $matches = [new PendingMatch(1, 1, 2), new PendingMatch(2, 1, 3), new PendingMatch(3, 2, 3)];

    $solution = (new GreedyScheduler)->schedule($context, $matches);

    expect($solution->scheduledCount())->toBe(3);
    expectNoPlayerClash($solution, $matches);
});

/**
 * Speler 3 is alleen op de eerste avond beschikbaar; zijn wedstrijden mogen
 * dus nooit op de tweede avond belanden.
 */
test('a player is never scheduled on a match day they are not available on', function () {
    $context = schedulingContext(
        days: [1 => ['fields' => [1]], 2 => ['fields' => [1]]],
        availability: [1 => [1, 2, 3], 2 => [1, 2]],
        settings: settings(break: 0),
    );
    $matches = [new PendingMatch(1, 1, 2), new PendingMatch(2, 1, 3), new PendingMatch(3, 2, 3)];

    $solution = (new GreedyScheduler)->schedule($context, $matches);

    expect($solution->scheduledCount())->toBe(3)
        ->and($solution->placements()[2]->matchDayId)->toBe(1)
        ->and($solution->placements()[3]->matchDayId)->toBe(1);
});

/**
 * Met een pauze in het raster: elke plaatsing moet op een bestaand slot van
 * de eigen speeldag liggen, dus binnen de openingstijden en nooit in de
 * pauze.
 */
test('every placement lies on a slot of its match day', function () {
    $context = schedulingContext([1 => ['fields' => [1, 2]]], [1 => [1, 2, 3, 4]]);
    $matches = [
        new PendingMatch(1, 1, 2), new PendingMatch(2, 1, 3), new PendingMatch(3, 1, 4),
        new PendingMatch(4, 2, 3), new PendingMatch(5, 2, 4), new PendingMatch(6, 3, 4),
    ];

    $solution = (new GreedyScheduler)->schedule($context, $matches);
    $grid = $context->matchDays[0]->grid;

    expect($solution->scheduledCount())->toBe(6);

    foreach ($solution->placements() as $placement) {
        $slot = $grid->slotStartingAt($placement->slot->startMinute);
        $insideBreak = $placement->slot->startMinute >= $grid->break['start_minute']
            && $placement->slot->startMinute < $grid->break['end_minute'];

        expect($slot?->index)->toBe($placement->slot->index)
            ->and($insideBreak)->toBeFalse()
            ->and($grid->matchEndMinute($placement->slot))->toBeLessThanOrEqual(ClockTime::toMinutes('23:00'));
    }
});

/**
 * Vier spelers, zes wedstrijden, maar iedereen mag er maar één per avond:
 * hoogstens twee wedstrijden passen er, de rest wordt gemeld.
 */
test('the maximum number of matches per player per day is never exceeded', function () {
    $context = schedulingContext([1 => ['fields' => [1, 2]]], [1 => [1, 2, 3, 4]], settings(break: 0, max: 1));
    $matches = [
        new PendingMatch(1, 1, 2), new PendingMatch(2, 1, 3), new PendingMatch(3, 1, 4),
        new PendingMatch(4, 2, 3), new PendingMatch(5, 2, 4), new PendingMatch(6, 3, 4),
    ];

    $solution = (new GreedyScheduler)->schedule($context, $matches);

    expect($solution->scheduledCount())->toBe(2)
        ->and($solution->unscheduledCount())->toBe(4);

    foreach ([1, 2, 3, 4] as $playerId) {
        expect($context->board->matchesOn($playerId, 1))->toBeLessThanOrEqual(1);
    }
});

/**
 * Een al gespeelde wedstrijd staat op tafel 1 in het eerste slot met speler
 * 3 erin. Die tafel is bezet en de wedstrijd telt mee voor de rust van
 * speler 3, dus zijn volgende wedstrijd schuift op.
 */
test('a pre-occupied slot blocks the table and counts towards the player', function () {
    $start = ClockTime::toMinutes('19:00');
    $board = new ScheduleBoard;
    $board->occupyTable(1, 1, $start, $start + 25);
    $board->occupyPlayer(3, 1, $start, $start + 20);

    $context = schedulingContext(
        days: [1 => ['fields' => [1, 2]]],
        availability: [1 => [1, 2, 3]],
        settings: settings(break: 0),
        board: $board,
    );

    $solution = (new GreedyScheduler)->schedule($context, [new PendingMatch(1, 1, 2), new PendingMatch(2, 1, 3)]);

    expect(placementKey($solution->placements()[1]))->toBe('1/2/0')
        ->and($solution->placements()[2]->slot->index)->toBe(2)
        ->and($context->board->matchesOn(3, 1))->toBe(2)
        ->and($solution->restViolations())->toBe([]);
});

/**
 * Speler 1 speelde al in het eerste slot. Het eerstvolgende vrije slot geeft
 * maar vijf minuten rust, het slot daarna dertig: dat laatste wint.
 */
test('the planner prefers a slot that respects the minimum rest', function () {
    $start = ClockTime::toMinutes('19:00');
    $board = new ScheduleBoard;
    $board->occupyTable(1, 1, $start, $start + 25);
    $board->occupyPlayer(1, 1, $start, $start + 20);

    $context = schedulingContext(
        days: [1 => ['fields' => [1]]],
        availability: [1 => [1, 2]],
        settings: settings(break: 0),
        board: $board,
    );

    $solution = (new GreedyScheduler)->schedule($context, [new PendingMatch(1, 1, 2)]);

    expect(placementKey($solution->placements()[1]))->toBe('1/1/2')
        ->and($solution->restViolations())->toBe([]);
});

/**
 * De avond heeft maar twee slots en het eerste is bezet door speler 1. Met
 * een uur minimale rust blijft alleen een slot met te weinig rust over: dat
 * wordt toch gepland en gemeld.
 */
test('a match is still scheduled when only a rest-violating slot remains and is reported', function () {
    $start = ClockTime::toMinutes('19:00');
    $board = new ScheduleBoard;
    $board->occupyTable(1, 1, $start, $start + 25);
    $board->occupyPlayer(1, 1, $start, $start + 20);

    $context = schedulingContext(
        days: [1 => ['ends_at' => '19:50', 'fields' => [1]]],
        availability: [1 => [1, 2]],
        settings: settings(rest: 60, break: 0),
        board: $board,
    );

    $solution = (new GreedyScheduler)->schedule($context, [new PendingMatch(1, 1, 2)]);

    expect($solution->scheduledCount())->toBe(1)
        ->and(placementKey($solution->placements()[1]))->toBe('1/1/1')
        ->and($solution->restViolations())->toBe([1]);
});

/**
 * Vier spelers spelen een volledige ronde op één avond met twee tafels en
 * negen slots: ruim genoeg voor alle zes de wedstrijden.
 */
test('every match is scheduled when capacity suffices', function () {
    $context = schedulingContext([1 => ['fields' => [1, 2]]], [1 => [1, 2, 3, 4]]);
    $matches = [
        new PendingMatch(1, 1, 2), new PendingMatch(2, 1, 3), new PendingMatch(3, 1, 4),
        new PendingMatch(4, 2, 3), new PendingMatch(5, 2, 4), new PendingMatch(6, 3, 4),
    ];

    $solution = (new GreedyScheduler)->schedule($context, $matches);

    expect($solution->scheduledCount())->toBe(6)
        ->and($solution->unscheduledCount())->toBe(0);
    expectNoPlayerClash($solution, $matches);
});

/**
 * De eerste avond heeft precies één slot. Het paar dat alleen díe avond
 * samen kan, moet het krijgen; het paar dat ook de tweede avond kan, wijkt
 * uit.
 */
test('a pair with only one shared match day is scheduled before less constrained pairs', function () {
    $context = schedulingContext(
        days: [
            1 => ['ends_at' => '19:25', 'fields' => [1]],
            2 => ['ends_at' => '20:15', 'fields' => [1]],
        ],
        availability: [1 => [1, 2, 3, 4], 2 => [3, 4]],
        settings: settings(break: 0),
    );

    $solution = (new GreedyScheduler)->schedule($context, [new PendingMatch(1, 3, 4), new PendingMatch(2, 1, 2)]);

    expect($solution->placements()[2]->matchDayId)->toBe(1)
        ->and($solution->placements()[1]->matchDayId)->toBe(2)
        ->and($solution->unscheduledCount())->toBe(0);
});

/**
 * Speler 1 speelt twee wedstrijden en beide avonden hebben plek: de
 * eerlijkheidsweging zet ze op verschillende avonden.
 */
test('matches of one player are spread across match days', function () {
    $context = schedulingContext(
        days: [1 => ['ends_at' => '20:15', 'fields' => [1]], 2 => ['ends_at' => '20:15', 'fields' => [1]]],
        availability: [1 => [1, 2, 3], 2 => [1, 2, 3]],
        settings: settings(break: 0),
    );

    $solution = (new GreedyScheduler)->schedule($context, [new PendingMatch(1, 1, 2), new PendingMatch(2, 1, 3)]);

    expect($solution->placements()[1]->matchDayId)->toBe(1)
        ->and($solution->placements()[2]->matchDayId)->toBe(2);
});

/**
 * Speler 3 kan alleen de eerste avond, speler 4 alleen de tweede: hun
 * onderlinge wedstrijd kan nergens heen, de andere wordt gewoon gepland.
 */
test('a pair without a shared match day is reported and does not stop the rest', function () {
    $context = schedulingContext(
        days: [1 => ['fields' => [1]], 2 => ['fields' => [1]]],
        availability: [1 => [1, 2, 3], 2 => [1, 2, 4]],
        settings: settings(break: 0),
    );

    $solution = (new GreedyScheduler)->schedule($context, [new PendingMatch(1, 3, 4), new PendingMatch(2, 1, 2)]);

    expect($solution->failures())->toBe([1 => SchedulingFailure::NoSharedMatchDay])
        ->and($solution->scheduledCount())->toBe(1)
        ->and($solution->placements())->toHaveKey(2);
});

/**
 * Er is ruimte zat, maar speler 1 zit na zijn eerste wedstrijd aan het
 * dagmaximum; alleen dat staat de tweede wedstrijd in de weg.
 */
test('a pair blocked only by the daily maximum is reported as such', function () {
    $context = schedulingContext(
        days: [1 => ['ends_at' => '20:15', 'fields' => [1]]],
        availability: [1 => [1, 2, 3]],
        settings: settings(break: 0, max: 1),
    );

    $solution = (new GreedyScheduler)->schedule($context, [new PendingMatch(1, 1, 2), new PendingMatch(2, 1, 3)]);

    expect($solution->failures())->toBe([2 => SchedulingFailure::MaxMatchesPerDayReached]);
});

/**
 * Eén slot op één tafel, twee wedstrijden zonder gedeelde spelers: de tweede
 * vindt geen vrije plek.
 */
test('a pair without a free slot is reported as no capacity', function () {
    $context = schedulingContext(
        days: [1 => ['ends_at' => '19:25', 'fields' => [1]]],
        availability: [1 => [1, 2, 3, 4]],
        settings: settings(break: 0),
    );

    $solution = (new GreedyScheduler)->schedule($context, [new PendingMatch(1, 1, 2), new PendingMatch(2, 3, 4)]);

    expect($solution->failures())->toBe([2 => SchedulingFailure::NoCapacity])
        ->and($solution->scheduledCount())->toBe(1);
});

/**
 * Determinisme: dezelfde invoer, twee verse contexten, hetzelfde schema.
 */
test('running the planner twice on the same input yields identical placements', function () {
    $matches = [
        new PendingMatch(1, 1, 2), new PendingMatch(2, 1, 3), new PendingMatch(3, 1, 4),
        new PendingMatch(4, 2, 3), new PendingMatch(5, 2, 4), new PendingMatch(6, 3, 4),
    ];
    $days = [1 => ['ends_at' => '21:00', 'fields' => [1, 2]], 2 => ['ends_at' => '21:00', 'fields' => [3]]];
    $availability = [1 => [1, 2, 3, 4], 2 => [1, 2, 3, 4]];

    $first = (new GreedyScheduler)->schedule(schedulingContext($days, $availability), $matches);
    $second = (new GreedyScheduler)->schedule(schedulingContext($days, $availability), $matches);

    expect(placementKeys($second))->toBe(placementKeys($first))
        ->and($first->scheduledCount())->toBe(6);
});

/**
 * De aanleververgelijking mag niet meetellen: de planner sorteert zelf op
 * beperking en id.
 */
test('the order in which matches are supplied does not change the result', function () {
    $matches = [
        new PendingMatch(1, 1, 2), new PendingMatch(2, 1, 3), new PendingMatch(3, 1, 4),
        new PendingMatch(4, 2, 3), new PendingMatch(5, 2, 4), new PendingMatch(6, 3, 4),
    ];
    $days = [1 => ['ends_at' => '21:00', 'fields' => [1, 2]], 2 => ['ends_at' => '21:00', 'fields' => [3]]];
    $availability = [1 => [1, 2, 3, 4], 2 => [1, 2, 3, 4]];

    $forwards = (new GreedyScheduler)->schedule(schedulingContext($days, $availability), $matches);
    $backwards = (new GreedyScheduler)->schedule(schedulingContext($days, $availability), array_reverse($matches));

    expect(placementKeys($backwards))->toBe(placementKeys($forwards));
});

/**
 * Zonder bezetting scoort elke plek gelijk; dan wint de eerste kandidaat.
 */
test('matches with equal scores are placed on the earliest day slot and field', function () {
    $context = schedulingContext(
        days: [1 => ['fields' => [1, 2]], 2 => ['fields' => [3]]],
        availability: [1 => [1, 2], 2 => [1, 2]],
        settings: settings(break: 0),
    );

    $solution = (new GreedyScheduler)->schedule($context, [new PendingMatch(1, 1, 2)]);

    expect(placementKey($solution->placements()[1]))->toBe('1/1/0');
});
