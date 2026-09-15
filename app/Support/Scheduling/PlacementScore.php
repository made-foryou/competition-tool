<?php

namespace App\Support\Scheduling;

/**
 * De score van een kandidaat-plek (lager is beter) plus of die plek de
 * minimale rust van een van beide spelers zou schenden.
 */
final readonly class PlacementScore
{
    public function __construct(
        public int $score,
        public bool $restViolated,
    ) {}
}
