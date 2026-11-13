<?php

namespace App\Support\Standings;

use InvalidArgumentException;

/**
 * Eén gespeelde wedstrijd voor het klassement, losgekoppeld van het model:
 * de twee spelers en hun gewonnen games.
 *
 * Een score hoort bij de speler in dezelfde kolom. De volgorde van de kolommen
 * zegt niets: het klassement rekent per kolom en gaat er nooit van uit dat de
 * laagste id als eerste staat. De canonieke volgorde geldt alleen voor
 * schrijvers via het model, niet voor de sync, de planner of testhelpers.
 *
 * Er is altijd precies één winnaar: het format dwingt dat af bij het
 * invoeren. Een gelijke score, een negatieve score of een speler tegen
 * zichzelf is daarom ongeldige invoer en geen randgeval om stil op te lossen.
 */
final readonly class PlayedMatch
{
    /**
     * @throws InvalidArgumentException bij dezelfde speler aan beide kanten, een negatieve score of een gelijke score
     */
    public function __construct(
        public int $firstPlayerId,
        public int $secondPlayerId,
        public int $firstPlayerScore,
        public int $secondPlayerScore,
    ) {
        if ($firstPlayerId === $secondPlayerId) {
            throw new InvalidArgumentException('A match cannot pair a player against themselves.');
        }

        if ($firstPlayerScore < 0 || $secondPlayerScore < 0) {
            throw new InvalidArgumentException('A score cannot be negative.');
        }

        if ($firstPlayerScore === $secondPlayerScore) {
            throw new InvalidArgumentException('A played match has exactly one winner.');
        }
    }
}
