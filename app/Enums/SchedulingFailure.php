<?php

namespace App\Enums;

/**
 * De reden waarom de planner een wedstrijd niet kon plaatsen, opgeslagen in
 * `matches.scheduling_failure`. Wordt gewist zodra de wedstrijd alsnog een
 * plek krijgt.
 */
enum SchedulingFailure: string
{
    /**
     * De twee spelers hebben geen enkele speeldag gemeen waarop ze allebei
     * beschikbaar zijn.
     */
    case NoSharedMatchDay = 'no_shared_match_day';

    /**
     * Er is nog wel een gedeelde speeldag, maar één van beide spelers zit al
     * aan `max_matches_per_day` op elke dag die overblijft.
     */
    case MaxMatchesPerDayReached = 'max_matches_per_day_reached';

    /**
     * Er is een gedeelde, niet-volle speeldag, maar geen enkel slot op geen
     * enkele tafel is op dat moment nog vrij voor allebei de spelers.
     */
    case NoCapacity = 'no_capacity';
}
