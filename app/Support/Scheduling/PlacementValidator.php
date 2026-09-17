<?php

namespace App\Support\Scheduling;

use App\Enums\PlacementViolation;

/**
 * De harde randvoorwaarden, op één plek. Zowel de planner als het handmatig
 * verplaatsen gebruikt deze klasse, zodat "hard" overal hetzelfde betekent.
 * De controles staan in een vaste volgorde: de eerste schending is de reden.
 *
 * Minimale rust hoort hier bewust niet bij; die is zacht en zit in
 * `GreedyScheduler::score()`.
 */
final readonly class PlacementValidator
{
    /**
     * De eerste harde schending van deze plek, of null als hij mag.
     */
    public function violation(Placement $placement, int $firstPlayerId, int $secondPlayerId, SchedulingContext $context): ?PlacementViolation
    {
        $matchDay = $context->matchDay($placement->matchDayId);

        if ($matchDay === null || ! $matchDay->hasField($placement->fieldId)) {
            return PlacementViolation::FieldNotOnMatchDay;
        }

        $slot = $matchDay->grid->slotStartingAt($placement->slot->startMinute);

        if ($slot === null || $slot->index !== $placement->slot->index || $slot->endMinute !== $placement->slot->endMinute) {
            return PlacementViolation::OutsideOpeningHours;
        }

        if (! $context->availability->isAvailable($firstPlayerId, $matchDay->matchDayId, $slot)
            || ! $context->availability->isAvailable($secondPlayerId, $matchDay->matchDayId, $slot)) {
            return PlacementViolation::PlayerUnavailable;
        }

        $maximum = $context->settings->maxMatchesPerPlayerPerDay;

        if ($maximum > 0
            && ($context->board->matchesOn($firstPlayerId, $matchDay->matchDayId) >= $maximum
                || $context->board->matchesOn($secondPlayerId, $matchDay->matchDayId) >= $maximum)) {
            return PlacementViolation::MaxMatchesReached;
        }

        $matchEndMinute = $matchDay->grid->matchEndMinute($slot);

        if (! $context->board->isPlayerFree($firstPlayerId, $matchDay->matchDayId, $slot->startMinute, $matchEndMinute)
            || ! $context->board->isPlayerFree($secondPlayerId, $matchDay->matchDayId, $slot->startMinute, $matchEndMinute)) {
            return PlacementViolation::PlayerBusy;
        }

        if (! $context->board->isTableFree($matchDay->matchDayId, $placement->fieldId, $slot->startMinute, $slot->endMinute)) {
            return PlacementViolation::TableOccupied;
        }

        return null;
    }
}
