<?php

namespace App\Support\Standings;

use App\Enums\PointsSystem;

/**
 * Het berekende klassement: de regels in volgorde, het aantal wedstrijden
 * dat meetelde en de puntentelling waarmee gerekend is.
 *
 * Een stand zonder meegetelde wedstrijd heeft wel regels (iedereen op nul);
 * of die getoond worden of een lege staat, beslist de presentatielaag op
 * basis van `hasResults()`.
 */
final readonly class Standings
{
    /**
     * @param  list<StandingRow>  $rows  in volgorde van het klassement
     */
    public function __construct(
        public array $rows,
        public int $playedMatchCount,
        public PointsSystem $pointsSystem,
    ) {}

    /**
     * Of er minstens één wedstrijd heeft meegeteld.
     */
    public function hasResults(): bool
    {
        return $this->playedMatchCount > 0;
    }

    /**
     * De regel van deze speler, of null als hij niet in de stand staat.
     */
    public function rowFor(int $playerId): ?StandingRow
    {
        foreach ($this->rows as $row) {
            if ($row->player->id === $playerId) {
                return $row;
            }
        }

        return null;
    }
}
