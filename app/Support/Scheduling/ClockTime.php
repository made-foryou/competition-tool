<?php

namespace App\Support\Scheduling;

/**
 * Rekent lokale kloktijden (`H:i`, zoals ze op de speeldag staan) om naar
 * minuten sinds middernacht en terug. Bewust puur: geen Carbon, geen
 * tijdzones — de planning kent alleen de klok van de speeldag zelf.
 */
final class ClockTime
{
    private function __construct() {}

    /**
     * Zet `19:05` of `19:05:00` om naar het aantal minuten sinds middernacht.
     */
    public static function toMinutes(string $clock): int
    {
        $parts = explode(':', $clock);

        $hours = (int) $parts[0];
        $minutes = (int) ($parts[1] ?? 0);

        return $hours * 60 + $minutes;
    }

    /**
     * Zet minuten sinds middernacht om naar `H:i` met voorloopnullen.
     */
    public static function fromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
