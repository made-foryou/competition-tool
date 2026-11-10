<?php

namespace App\Enums;

/**
 * De status van een wedstrijd. `Pending` wedstrijden mogen door de
 * deelnemers-sync verwijderd en herverdeeld worden; zodra een wedstrijd
 * `Played` is (uitslag geregistreerd) blijft hij onaantastbaar voor die sync.
 *
 * De uitslag wordt geregistreerd via `RecordMatchResult`, die de wedstrijd op
 * `Played` zet; `ClearMatchResult` zet hem weer terug op `Pending`.
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
