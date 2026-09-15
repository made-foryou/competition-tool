<?php

namespace App\Support\Scheduling;

/**
 * De bezetting tijdens het plannen: welke tafels en welke spelers op welke
 * momenten bezet zijn, plus het aantal wedstrijden per speler per speeldag.
 *
 * Bezetting wordt als tijdsinterval bijgehouden en niet als slot-index, zodat
 * een gespeelde of vastgezette wedstrijd die niet meer op het huidige raster
 * ligt (instellingen of openingstijden gewijzigd) toch correct blokkeert.
 * Intervallen zijn halfopen: [start, eind) overlapt [a, b) als
 * `start < b && a < eind`, dus aansluitende wedstrijden botsen niet.
 */
final class ScheduleBoard
{
    /**
     * Bezette tijdvakken per tafel, gesleuteld op speeldag en tafel.
     *
     * @var array<string, list<array{int, int}>>
     */
    private array $tables = [];

    /**
     * Bezette tijdvakken per speler, gesleuteld op speeldag en speler en
     * altijd op begintijd gesorteerd — `occupyPlayer()` voegt op de juiste
     * plek in, zodat lezen geen sortering meer kost.
     *
     * @var array<string, list<array{int, int}>>
     */
    private array $players = [];

    /**
     * Aantal wedstrijden per speler per speeldag, zelfde sleutel als de
     * spelersintervallen.
     *
     * @var array<string, int>
     */
    private array $counts = [];

    /**
     * Legt een tafel vast voor [start, eind), wisseltijd inbegrepen.
     */
    public function occupyTable(int $matchDayId, int $fieldId, int $startMinute, int $endMinute): void
    {
        $this->tables[$this->key($matchDayId, $fieldId)][] = [$startMinute, $endMinute];
    }

    /**
     * Legt een speler vast voor [start, eind) en telt de wedstrijd mee voor
     * het dagmaximum.
     */
    public function occupyPlayer(int $playerId, int $matchDayId, int $startMinute, int $endMinute): void
    {
        $key = $this->key($matchDayId, $playerId);
        $intervals = $this->players[$key] ?? [];
        $position = count($intervals);

        foreach ($intervals as $index => $interval) {
            if ($startMinute < $interval[0]) {
                $position = $index;

                break;
            }
        }

        array_splice($intervals, $position, 0, [[$startMinute, $endMinute]]);

        $this->players[$key] = $intervals;
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
    }

    public function isTableFree(int $matchDayId, int $fieldId, int $startMinute, int $endMinute): bool
    {
        return $this->isFree($this->tables[$this->key($matchDayId, $fieldId)] ?? [], $startMinute, $endMinute);
    }

    public function isPlayerFree(int $playerId, int $matchDayId, int $startMinute, int $endMinute): bool
    {
        return $this->isFree($this->players[$this->key($matchDayId, $playerId)] ?? [], $startMinute, $endMinute);
    }

    /**
     * Het aantal wedstrijden dat een speler op die speeldag al heeft.
     */
    public function matchesOn(int $playerId, int $matchDayId): int
    {
        return $this->counts[$this->key($matchDayId, $playerId)] ?? 0;
    }

    /**
     * De bezette tijdvakken van een speler op die speeldag, op begintijd
     * gesorteerd.
     *
     * @return list<array{int, int}>
     */
    public function intervalsOf(int $playerId, int $matchDayId): array
    {
        return $this->players[$this->key($matchDayId, $playerId)] ?? [];
    }

    /**
     * @param  list<array{int, int}>  $intervals
     */
    private function isFree(array $intervals, int $startMinute, int $endMinute): bool
    {
        foreach ($intervals as $interval) {
            if ($interval[0] < $endMinute && $startMinute < $interval[1]) {
                return false;
            }
        }

        return true;
    }

    private function key(int $matchDayId, int $subjectId): string
    {
        return $matchDayId.':'.$subjectId;
    }
}
