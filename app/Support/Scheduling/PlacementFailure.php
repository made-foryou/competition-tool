<?php

namespace App\Support\Scheduling;

use App\Enums\PlacementViolation;

/**
 * Een afgewezen plek: welke harde randvoorwaarde geschonden werd en -- als de
 * schending over een speler gaat -- om welke speler het ging.
 *
 * De planner heeft aan de schending genoeg; het handmatig verplaatsen zet de
 * speler in de foutmelding, zodat de beheerder niet hoeft te raden wie van de
 * twee in de weg zit. `playerId` is daarom `null` bij een schending die niets
 * met een speler te maken heeft (tafel, speeldag, slot).
 */
final readonly class PlacementFailure
{
    public function __construct(
        public PlacementViolation $violation,
        public ?int $playerId = null,
    ) {}
}
