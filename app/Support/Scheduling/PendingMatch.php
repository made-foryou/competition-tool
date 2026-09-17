<?php

namespace App\Support\Scheduling;

/**
 * Een nog in te plannen wedstrijd, losgekoppeld van het model: alleen het id
 * en de twee spelers.
 */
final readonly class PendingMatch
{
    public function __construct(
        public int $id,
        public int $firstPlayerId,
        public int $secondPlayerId,
    ) {}
}
