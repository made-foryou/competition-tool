<?php

namespace App\Support\Scheduling;

use App\Enums\SchedulingFailure;

/**
 * Het resultaat van een planningsronde: welke wedstrijd waar terechtkwam,
 * welke niet en waarom, en bij welke de minimale rust niet gehaald werd.
 */
final class SchedulingSolution
{
    /**
     * @var array<int, Placement>
     */
    private array $placements = [];

    /**
     * @var array<int, SchedulingFailure>
     */
    private array $failures = [];

    /**
     * Als set op wedstrijd-id, zodat een wedstrijd er nooit dubbel in komt.
     *
     * @var array<int, int>
     */
    private array $restViolations = [];

    public function place(int $matchId, Placement $placement, bool $restViolated): void
    {
        $this->placements[$matchId] = $placement;

        if ($restViolated) {
            $this->restViolations[$matchId] = $matchId;
        }
    }

    public function fail(int $matchId, SchedulingFailure $reason): void
    {
        $this->failures[$matchId] = $reason;
    }

    /**
     * De plaatsingen per wedstrijd-id, in de volgorde waarin ze geplaatst
     * zijn.
     *
     * @return array<int, Placement>
     */
    public function placements(): array
    {
        return $this->placements;
    }

    /**
     * @return array<int, SchedulingFailure>
     */
    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * De wedstrijden die wel gepland zijn, maar met te weinig rust voor
     * minstens één speler.
     *
     * @return list<int>
     */
    public function restViolations(): array
    {
        return array_values($this->restViolations);
    }

    public function scheduledCount(): int
    {
        return count($this->placements);
    }

    public function unscheduledCount(): int
    {
        return count($this->failures);
    }
}
