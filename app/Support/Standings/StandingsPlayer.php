<?php

namespace App\Support\Standings;

/**
 * Een speler in het klassement, losgekoppeld van het model: alleen wat de
 * stand toont. `displayName` is de weergavenaam (de nickname, anders de
 * naam), zodat een stand nooit een echte naam lekt die achter een nickname
 * zit.
 *
 * `isParticipant` is onwaar voor een vertrokken deelnemer: iemand met een
 * gespeelde wedstrijd die niet meer aan de competitie gekoppeld is. Die blijft
 * in de stand staan, met zijn uitslagen.
 */
final readonly class StandingsPlayer
{
    public function __construct(
        public int $id,
        public string $displayName,
        public bool $isParticipant,
    ) {}
}
