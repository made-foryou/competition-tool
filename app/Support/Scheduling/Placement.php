<?php

namespace App\Support\Scheduling;

/**
 * Een plek voor een wedstrijd: een tafel op een speeldag, op een slot.
 */
final readonly class Placement
{
    public function __construct(
        public int $matchDayId,
        public int $fieldId,
        public Slot $slot,
    ) {}
}
