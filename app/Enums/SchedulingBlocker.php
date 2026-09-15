<?php

namespace App\Enums;

/**
 * Precondities die het plannen voor een hele competitie blokkeren nog voordat
 * de planner een wedstrijd bekijkt. Gaat nooit de database in: dezelfde
 * waarde bepaalt zowel de knop-hint in de UI als de toast na indrukken, zodat
 * die nooit uiteenlopen. Volgorde is prioriteit.
 */
enum SchedulingBlocker: string
{
    /**
     * De competitie heeft geen speeldagen om op te plannen.
     */
    case NoMatchDays = 'no_match_days';

    /**
     * Geen enkele speeldag heeft tafels.
     */
    case NoFields = 'no_fields';

    /**
     * Geen enkele huidige deelnemer heeft beschikbaarheid ingevuld.
     */
    case NoAvailability = 'no_availability';

    /**
     * Er zijn geen openstaande wedstrijden om in te plannen.
     */
    case NoMatches = 'no_matches';

    /**
     * Alleen bij aanvullen: alles is al gepland. Informatieve toast, geen
     * echte fout.
     */
    case NothingToSchedule = 'nothing_to_schedule';
}
