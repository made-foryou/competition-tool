<?php

namespace App\Support\Scheduling;

/**
 * De ingevulde beschikbaarheid, binair per speeldag. Elke
 * beschikbaarheidscheck van de planner loopt door `isAvailable()`, zodat
 * beschikbaarheid per tijdvak later zonder herontwerp toegevoegd kan worden.
 */
final readonly class AvailabilityLookup
{
    /**
     * @var array<int, array<int, true>>
     */
    private array $available;

    /**
     * @param  array<int, list<int>>  $availableByMatchDay  speeldag-id => ids van de beschikbare spelers
     */
    public function __construct(array $availableByMatchDay)
    {
        $available = [];

        foreach ($availableByMatchDay as $matchDayId => $playerIds) {
            $available[$matchDayId] = array_fill_keys($playerIds, true);
        }

        $this->available = $available;
    }

    /**
     * Of een speler op die speeldag beschikbaar is. `$slot` wordt nu bewust
     * genegeerd: beschikbaarheid is binair per speeldag (besluit 7). De
     * parameter staat er zodat beschikbaarheid per tijdvak later alleen deze
     * methode raakt en niet het algoritme eromheen.
     */
    public function isAvailable(int $playerId, int $matchDayId, ?Slot $slot = null): bool
    {
        return isset($this->available[$matchDayId][$playerId]);
    }

    /**
     * De speeldagen waarop beide spelers beschikbaar zijn, in de volgorde
     * waarin de speeldagen zijn meegegeven.
     *
     * @param  list<int>  $orderedMatchDayIds
     * @return list<int>
     */
    public function sharedMatchDayIds(int $first, int $second, array $orderedMatchDayIds): array
    {
        $shared = [];

        foreach ($orderedMatchDayIds as $matchDayId) {
            if ($this->isAvailable($first, $matchDayId) && $this->isAvailable($second, $matchDayId)) {
                $shared[] = $matchDayId;
            }
        }

        return $shared;
    }
}
