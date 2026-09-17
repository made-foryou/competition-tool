<?php

namespace App\Support\Scheduling;

/**
 * Eén tijdslot op een speeldag. `endMinute` is inclusief de wisseltijd en is
 * dus de lengte die een tafel bezet houdt; de wedstrijd zelf eindigt eerder
 * (zie `SlotGrid::matchEndMinute()`).
 */
final readonly class Slot
{
    public function __construct(
        public int $index,
        public int $startMinute,
        public int $endMinute,
    ) {}

    /**
     * Het begin van het slot als kloktijd (`H:i`).
     */
    public function startsAt(): string
    {
        return ClockTime::fromMinutes($this->startMinute);
    }

    /**
     * Het einde van het slot, wisseltijd inbegrepen, als kloktijd (`H:i`).
     */
    public function endsAt(): string
    {
        return ClockTime::fromMinutes($this->endMinute);
    }
}
