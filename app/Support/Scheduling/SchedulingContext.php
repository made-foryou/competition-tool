<?php

namespace App\Support\Scheduling;

use App\Support\CompetitionSettings;

/**
 * Alles wat de planner nodig heeft: de instellingen, de speeldagen in vaste
 * volgorde, de beschikbaarheid en de bezetting. De bouwer levert de
 * speeldagen al gesorteerd op datum, begintijd en id aan; die volgorde
 * bepaalt mede het deterministische resultaat.
 */
final readonly class SchedulingContext
{
    /**
     * @param  list<MatchDaySchedule>  $matchDays
     */
    public function __construct(
        public CompetitionSettings $settings,
        public array $matchDays,
        public AvailabilityLookup $availability,
        public ScheduleBoard $board,
    ) {}

    public function matchDay(int $id): ?MatchDaySchedule
    {
        foreach ($this->matchDays as $matchDay) {
            if ($matchDay->matchDayId === $id) {
                return $matchDay;
            }
        }

        return null;
    }

    /**
     * De ids van de speeldagen, in volgorde.
     *
     * @return list<int>
     */
    public function matchDayIds(): array
    {
        return array_map(fn (MatchDaySchedule $matchDay): int => $matchDay->matchDayId, $this->matchDays);
    }
}
