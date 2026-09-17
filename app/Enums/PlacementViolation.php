<?php

namespace App\Enums;

/**
 * Een harde randvoorwaarde die geschonden wordt als een wedstrijd op een
 * bepaalde plek (speeldag, tafel, slot) gezet zou worden. Gaat nooit de
 * database in: de planner gebruikt hem om kandidaten af te wijzen en het
 * handmatig verplaatsen om een veldfout te tonen. Volgorde is de vaste
 * controlevolgorde van `PlacementValidator`.
 */
enum PlacementViolation: string
{
    /**
     * De tafel hoort niet bij de gekozen speeldag (of de speeldag bestaat
     * niet binnen deze planning).
     */
    case FieldNotOnMatchDay = 'field_not_on_match_day';

    /**
     * Het slot ligt niet in het raster van de speeldag: buiten de
     * openingstijden of midden in de pauze.
     */
    case OutsideOpeningHours = 'outside_opening_hours';

    /**
     * Minstens een van beide spelers is niet beschikbaar op die speeldag.
     */
    case PlayerUnavailable = 'player_unavailable';

    /**
     * Minstens een van beide spelers zit al aan `max_matches_per_day`.
     */
    case MaxMatchesReached = 'max_matches_reached';

    /**
     * Minstens een van beide spelers speelt op dat moment al een andere
     * wedstrijd.
     */
    case PlayerBusy = 'player_busy';

    /**
     * De tafel is op dat moment al bezet, wisseltijd meegerekend.
     */
    case TableOccupied = 'table_occupied';
}
