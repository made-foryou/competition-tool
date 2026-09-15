<?php

namespace App\Support\Scheduling;

use App\Enums\SchedulingBlocker;
use App\Enums\SchedulingFailure;
use App\Enums\SchedulingMode;

/**
 * De uitkomst van één planningsronde van `ScheduleCompetitionMatches`: wat er
 * geplaatst is, wat er onaangeroerd bleef, wat er overbleef en waarom.
 *
 * Een geblokkeerde ronde is geen fout maar een normale toestand (een lege
 * competitie hoort niet te crashen), dus ook die komt als resultaat terug —
 * met `blocker` gevuld en alle tellers op nul, omdat er dan niets gelezen of
 * geschreven is.
 */
final readonly class SchedulingResult
{
    /**
     * @param  int  $scheduledCount  in deze ronde geplaatst
     * @param  int  $keptCount  stond al volledig gepland en is niet aangeraakt (inclusief gespeeld en vastgezet)
     * @param  int  $unscheduledCount  na deze ronde nog zonder plek
     * @param  array<int, SchedulingFailure>  $failures  wedstrijd-id => reden
     * @param  list<int>  $restViolations  wedstrijd-ids die met te weinig rust geplaatst zijn
     */
    public function __construct(
        public SchedulingMode $mode,
        public int $scheduledCount,
        public int $keptCount,
        public int $unscheduledCount,
        public array $failures,
        public array $restViolations,
        public ?SchedulingBlocker $blocker = null,
    ) {}

    /**
     * Een ronde die door een preconditie niet eens begonnen is.
     */
    public static function blocked(SchedulingMode $mode, SchedulingBlocker $blocker): self
    {
        return new self(
            mode: $mode,
            scheduledCount: 0,
            keptCount: 0,
            unscheduledCount: 0,
            failures: [],
            restViolations: [],
            blocker: $blocker,
        );
    }

    public function isBlocked(): bool
    {
        return $this->blocker instanceof SchedulingBlocker;
    }

    /**
     * Of er na deze ronde geen enkele wedstrijd meer zonder plek zit.
     */
    public function isComplete(): bool
    {
        return ! $this->isBlocked() && $this->unscheduledCount === 0;
    }
}
