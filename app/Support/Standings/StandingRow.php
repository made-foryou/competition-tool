<?php

namespace App\Support\Standings;

/**
 * Eén regel van het klassement: de positie, de speler en zijn telling.
 *
 * Een gedeelde plaats krijgt voor alle spelers hetzelfde nummer, en het
 * volgende nummer slaat de gedeelde plaatsen over (1, 2, 2, 4).
 * `sharesPosition` is waar voor elke regel waarvan de positie door minstens
 * twee regels gedeeld wordt.
 */
final readonly class StandingRow
{
    public function __construct(
        public int $position,
        public bool $sharesPosition,
        public StandingsPlayer $player,
        public int $played,
        public int $won,
        public int $lost,
        public int $gamesFor,
        public int $gamesAgainst,
        public int $points,
    ) {}

    /**
     * Het game-saldo: gewonnen min verloren games. De eerste tiebreak bij
     * gelijke punten.
     */
    public function gameDifference(): int
    {
        return $this->gamesFor - $this->gamesAgainst;
    }
}
