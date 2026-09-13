<?php

namespace App\Enums;

/**
 * De status van een wedstrijd. `Pending` wedstrijden mogen door de
 * deelnemers-sync verwijderd en herverdeeld worden; zodra een wedstrijd
 * `Played` is (uitslag geregistreerd, registratie zelf volgt in een later
 * issue) blijft hij onaantastbaar voor die sync.
 */
enum MatchStatus: string
{
    /**
     * Nog te spelen; onderdeel van de door de sync beheerde wedstrijdenlijst.
     */
    case Pending = 'pending';

    /**
     * Uitslag is geregistreerd. Wordt nooit verwijderd door de sync.
     */
    case Played = 'played';
}
