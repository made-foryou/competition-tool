<?php

namespace App\Enums;

/**
 * De puntentelling voor het klassement van een competitie. De keuze is een
 * instelling per competitie (besluit Menno #58) en vergrendelt samen met het
 * wedstrijdformat na de eerste uitslag, zodat alle uitslagen met dezelfde
 * telling worden opgeteld. Alleen gespeelde wedstrijden tellen mee.
 */
enum PointsSystem: string
{
    /**
     * Winst levert 2 punten op, verlies niets. Standaard.
     */
    case WinTwoLossZero = 'win-2-loss-0';

    /**
     * Winst levert 2 punten op, verlies 1 punt, zoals het ITTF-reglement voor poules.
     */
    case WinTwoLossOne = 'win-2-loss-1';

    /**
     * Elke gewonnen game levert 1 punt op, ook in een verloren wedstrijd.
     */
    case GamesWon = 'games-won';

    /**
     * Het aantal punten voor een speler in een wedstrijd. De winnaar is de
     * speler met de hoogste score: gelijkspel bestaat niet, het format
     * dwingt precies één winnaar af.
     */
    public function pointsFor(int $ownGames, int $opponentGames): int
    {
        $isWinner = $ownGames > $opponentGames;

        return match ($this) {
            self::WinTwoLossZero => $isWinner ? 2 : 0,
            self::WinTwoLossOne => $isWinner ? 2 : 1,
            self::GamesWon => $ownGames,
        };
    }
}
