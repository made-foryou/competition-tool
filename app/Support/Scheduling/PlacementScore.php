<?php

namespace App\Support\Scheduling;

/**
 * De score van een kandidaat-plek als drietal, lager is beter. De drie
 * onderdelen worden **lexicografisch** vergeleken en niet als gewogen som
 * opgeteld: rust is daarmee een eigenschap van de uitkomst en geen aanname
 * over gewichten die op een drukke avond omvalt.
 *
 * 1. `restViolations` — het aantal spelers (0, 1 of 2) dat te weinig rust zou
 *    krijgen;
 * 2. `fairness` — het aantal wedstrijden dat beide spelers die dag samen al
 *    hebben;
 * 3. `waitMinutes` — de opgetelde wachttijd boven op de minimale rust.
 */
final readonly class PlacementScore
{
    /**
     * Of deze plek de minimale rust van minstens één van beide spelers
     * schendt; afgeleid van `restViolations` zodat beide nooit uiteen lopen.
     */
    public bool $restViolated;

    public function __construct(
        public int $restViolations,
        public int $fairness,
        public int $waitMinutes,
    ) {
        $this->restViolated = $restViolations > 0;
    }

    /**
     * Of deze score strikt beter is dan de andere: eerst op rustschendingen,
     * dan op eerlijkheid en pas als die gelijk zijn op wachttijd.
     */
    public function isBetterThan(self $other): bool
    {
        return ([$this->restViolations, $this->fairness, $this->waitMinutes]
            <=> [$other->restViolations, $other->fairness, $other->waitMinutes]) < 0;
    }
}
