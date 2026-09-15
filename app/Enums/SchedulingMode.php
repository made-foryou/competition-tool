<?php

namespace App\Enums;

/**
 * De twee manieren waarop `ScheduleCompetitionMatches` een competitie
 * inplant.
 */
enum SchedulingMode: string
{
    /**
     * Alleen de nog ongeplande wedstrijden aanvullen rond het bestaande
     * schema; geplande, gespeelde en vastgezette wedstrijden blijven staan.
     */
    case Fill = 'fill';

    /**
     * Alles loslaten behalve gespeelde en vastgezette wedstrijden, en de
     * planning opnieuw berekenen.
     */
    case Reschedule = 'reschedule';
}
