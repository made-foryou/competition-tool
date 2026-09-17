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
     *
     * De planner heeft alleen de reden nodig; wie meer wil weten (welke van
     * beide spelers de schending veroorzaakt) gebruikt `failure()`.
     */
    public function violation(Placement $placement, int $firstPlayerId, int $secondPlayerId, SchedulingContext $context): ?PlacementViolation
    {
        return $this->failure($placement, $firstPlayerId, $secondPlayerId, $context)?->violation;
    }

    /**
     * Dezelfde controle als `violation()`, maar met de betrokken speler erbij
     * waar de schending over een speler gaat. Eén implementatie voor beide,
     * zodat de planner en het handmatig verplaatsen nooit uiteen kunnen lopen.
     */
    public function failure(Placement $placement, int $firstPlayerId, int $secondPlayerId, SchedulingContext $context): ?PlacementFailure
    {
        $matchDay = $context->matchDay($placement->matchDayId);

        if ($matchDay === null || ! $matchDay->hasField($placement->fieldId)) {
            return new PlacementFailure(PlacementViolation::FieldNotOnMatchDay);
        }

        $slot = $matchDay->grid->slotStartingAt($placement->slot->startMinute);

        if ($slot === null || $slot->index !== $placement->slot->index || $slot->endMinute !== $placement->slot->endMinute) {
            return new PlacementFailure(PlacementViolation::OutsideOpeningHours);
        }

        foreach ([$firstPlayerId, $secondPlayerId] as $playerId) {
            if (! $context->availability->isAvailable($playerId, $matchDay->matchDayId, $slot)) {
                return new PlacementFailure(PlacementViolation::PlayerUnavailable, $playerId);
            }
        }

        $maximum = $context->settings->maxMatchesPerPlayerPerDay;

        if ($maximum > 0) {
            foreach ([$firstPlayerId, $secondPlayerId] as $playerId) {
                if ($context->board->matchesOn($playerId, $matchDay->matchDayId) >= $maximum) {
                    return new PlacementFailure(PlacementViolation::MaxMatchesReached, $playerId);
                }
            }
        }

        $matchEndMinute = $matchDay->grid->matchEndMinute($slot);

        foreach ([$firstPlayerId, $secondPlayerId] as $playerId) {
            if (! $context->board->isPlayerFree($playerId, $matchDay->matchDayId, $slot->startMinute, $matchEndMinute)) {
                return new PlacementFailure(PlacementViolation::PlayerBusy, $playerId);
            }
        }

        if (! $context->board->isTableFree($matchDay->matchDayId, $placement->fieldId, $slot->startMinute, $slot->endMinute)) {
            return new PlacementFailure(PlacementViolation::TableOccupied);
        }

        return null;
    }
}
