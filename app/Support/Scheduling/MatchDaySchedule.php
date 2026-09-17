<?php

namespace App\Support\Scheduling;

/**
 * Eén speeldag zoals de planner hem ziet: de datum en begintijd (alleen om
 * de volgorde en de weergave vast te leggen), de tafels in vaste volgorde en
 * het slotraster van die avond.
 */
final readonly class MatchDaySchedule
{
    /**
     * @param  string  $date  de datum als `Y-m-d`
     * @param  string  $startsAt  de begintijd als `H:i`
     * @param  list<int>  $fieldIds  tafels, al gesorteerd op positie en id
     */
    public function __construct(
        public int $matchDayId,
        public string $date,
        public string $startsAt,
        public array $fieldIds,
        public SlotGrid $grid,
    ) {}

    public function hasField(int $fieldId): bool
    {
        return in_array($fieldId, $this->fieldIds, true);
    }

    /**
     * Of er überhaupt iets te plannen valt: zonder tafels of zonder slots is
     * de dag onbruikbaar.
     */
    public function hasCapacity(): bool
    {
        return $this->fieldIds !== [] && ! $this->grid->isEmpty();
    }
}
