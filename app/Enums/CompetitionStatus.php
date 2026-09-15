<?php

namespace App\Enums;

enum CompetitionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Finished = 'finished';

    /**
     * Of je je voor deze competitie kunt aanmelden -- via de inschrijfpagina
     * of via een uitnodiging, met of zonder bestaand account. Eén lijn voor
     * alle routes: alleen een actieve competitie neemt aanmeldingen aan. Een
     * concept-competitie is nog niet aangekondigd en een afgeronde neemt
     * niemand meer aan; koppelen zou daar via SyncCompetitionMatches alsnog
     * pending-wedstrijden in een afgesloten toernooi schrijven.
     *
     * Een beheerder die zelf een deelnemer koppelt doet iets anders -- die
     * bouwt de deelnemerslijst op, ook tijdens de conceptfase -- en valt dus
     * niet onder deze regel.
     */
    public function allowsSignUp(): bool
    {
        return $this === self::Active;
    }
}
