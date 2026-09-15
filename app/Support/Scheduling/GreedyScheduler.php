<?php

namespace App\Support\Scheduling;

use App\Enums\PlacementViolation;
use App\Enums\SchedulingFailure;

/**
 * Het greedy toewijzingsalgoritme: de meest beperkte wedstrijden eerst, per
 * wedstrijd de best scorende plek. Volledig deterministisch — geen
 * randomisatie, alle invoer in een vaste volgorde en bij gelijke score wint
 * de eerste kandidaat (vroegste dag, vroegste slot, laagste tafel).
 */
final readonly class GreedyScheduler
{
    /**
     * Een geschonden rustpauze weegt zo zwaar dat een plek zonder schending
     * altijd wint, ook op een latere avond: de penalty is groter dan elk
     * realistisch verschil in eerlijkheid plus wachttijd.
     */
    public const int REST_VIOLATION_PENALTY = 1000;

    /**
     * Eerlijkheid gaat vóór compactheid: één extra wedstrijd op dezelfde
     * avond weegt zwaarder dan tot 100 minuten extra wachttijd, dus spreidt
     * de planner een speler eerst over de avonden.
     */
    public const int FAIRNESS_WEIGHT = 100;

    /**
     * Wachttijd telt per minuut en is daarmee de fijnregeling die alleen
     * beslist als rust en eerlijkheid gelijk uitvallen.
     */
    public const int WAIT_WEIGHT = 1;

    public function __construct(
        private PlacementValidator $validator = new PlacementValidator,
    ) {}

    /**
     * Plant de wedstrijden in en bezet ze meteen op het bord van de context,
     * zodat volgende wedstrijden rekening houden met de zojuist gemaakte
     * keuzes. Muteert dus `$context->board`.
     *
     * @param  list<PendingMatch>  $matches
     */
    public function schedule(SchedulingContext $context, array $matches): SchedulingSolution
    {
        $solution = new SchedulingSolution;
        $matchDayIds = $context->matchDayIds();

        /** @var list<array{match: PendingMatch, shared: list<int>}> $ordered */
        $ordered = array_map(fn (PendingMatch $match): array => [
            'match' => $match,
            'shared' => $context->availability->sharedMatchDayIds($match->firstPlayerId, $match->secondPlayerId, $matchDayIds),
        ], $matches);

        usort($ordered, fn (array $first, array $second): int => [count($first['shared']), $first['match']->id] <=> [count($second['shared']), $second['match']->id]);

        foreach ($ordered as $entry) {
            $this->scheduleOne($entry['match'], $entry['shared'], $context, $solution);
        }

        return $solution;
    }

    /**
     * De score van een plek, lager is beter: rustschendingen wegen het
     * zwaarst, daarna het aantal wedstrijden dat beide spelers die dag al
     * hebben, en als fijnregeling de wachttijd tot de dichtstbijzijnde eigen
     * wedstrijd.
     */
    public function score(Placement $placement, int $firstPlayerId, int $secondPlayerId, SchedulingContext $context): PlacementScore
    {
        $matchDay = $context->matchDay($placement->matchDayId);

        if ($matchDay === null) {
            return new PlacementScore(0, false);
        }

        $startMinute = $placement->slot->startMinute;
        $matchEndMinute = $matchDay->grid->matchEndMinute($placement->slot);
        $minRestMinutes = $context->settings->minRestMinutes;

        $first = $this->rate($firstPlayerId, $matchDay->matchDayId, $startMinute, $matchEndMinute, $minRestMinutes, $context->board);
        $second = $this->rate($secondPlayerId, $matchDay->matchDayId, $startMinute, $matchEndMinute, $minRestMinutes, $context->board);

        $restViolations = (int) $first['rest_violated'] + (int) $second['rest_violated'];
        $matchesToday = $context->board->matchesOn($firstPlayerId, $matchDay->matchDayId)
            + $context->board->matchesOn($secondPlayerId, $matchDay->matchDayId);

        $score = self::REST_VIOLATION_PENALTY * $restViolations
            + self::FAIRNESS_WEIGHT * $matchesToday
            + self::WAIT_WEIGHT * ($first['wait'] + $second['wait']);

        return new PlacementScore($score, $first['rest_violated'] || $second['rest_violated']);
    }

    /**
     * Zoekt de beste plek voor één wedstrijd en bezet die, of legt vast
     * waarom het niet lukte.
     *
     * @param  list<int>  $sharedMatchDayIds
     */
    private function scheduleOne(PendingMatch $match, array $sharedMatchDayIds, SchedulingContext $context, SchedulingSolution $solution): void
    {
        $best = null;
        $bestScore = null;

        /** @var array<string, PlacementViolation> $violations */
        $violations = [];

        foreach ($sharedMatchDayIds as $matchDayId) {
            $matchDay = $context->matchDay($matchDayId);

            if ($matchDay === null) {
                continue;
            }

            foreach ($matchDay->grid->slots as $slot) {
                foreach ($matchDay->fieldIds as $fieldId) {
                    $placement = new Placement($matchDayId, $fieldId, $slot);
                    $violation = $this->validator->violation($placement, $match->firstPlayerId, $match->secondPlayerId, $context);

                    if ($violation instanceof PlacementViolation) {
                        $violations[$violation->value] = $violation;

                        continue;
                    }

                    $score = $this->score($placement, $match->firstPlayerId, $match->secondPlayerId, $context);

                    if ($bestScore === null || $score->score < $bestScore->score) {
                        $best = $placement;
                        $bestScore = $score;
                    }
                }
            }
        }

        if ($best === null || $bestScore === null) {
            $solution->fail($match->id, $this->diagnose($sharedMatchDayIds, $violations));

            return;
        }

        $matchDay = $context->matchDay($best->matchDayId);
        $matchEndMinute = $matchDay instanceof MatchDaySchedule
            ? $matchDay->grid->matchEndMinute($best->slot)
            : $best->slot->endMinute;

        $context->board->occupyTable($best->matchDayId, $best->fieldId, $best->slot->startMinute, $best->slot->endMinute);
        $context->board->occupyPlayer($match->firstPlayerId, $best->matchDayId, $best->slot->startMinute, $matchEndMinute);
        $context->board->occupyPlayer($match->secondPlayerId, $best->matchDayId, $best->slot->startMinute, $matchEndMinute);

        $solution->place($match->id, $best, $bestScore->restViolated);
    }

    /**
     * De reden waarom er geen enkele kandidaat overbleef.
     *
     * @param  list<int>  $sharedMatchDayIds
     * @param  array<string, PlacementViolation>  $violations
     */
    private function diagnose(array $sharedMatchDayIds, array $violations): SchedulingFailure
    {
        if ($sharedMatchDayIds === []) {
            return SchedulingFailure::NoSharedMatchDay;
        }

        if ($violations !== [] && array_keys($violations) === [PlacementViolation::MaxMatchesReached->value]) {
            return SchedulingFailure::MaxMatchesPerDayReached;
        }

        return SchedulingFailure::NoCapacity;
    }

    /**
     * De rust en wachttijd van één speler rond deze plek. Overlap kan niet
     * voorkomen: de validator heeft die kandidaten al afgewezen.
     *
     * @return array{rest_violated: bool, wait: int}
     */
    private function rate(int $playerId, int $matchDayId, int $startMinute, int $matchEndMinute, int $minRestMinutes, ScheduleBoard $board): array
    {
        $nearestGap = null;

        foreach ($board->intervalsOf($playerId, $matchDayId) as [$intervalStart, $intervalEnd]) {
            if ($intervalEnd <= $startMinute) {
                $gap = $startMinute - $intervalEnd;
            } elseif ($matchEndMinute <= $intervalStart) {
                $gap = $intervalStart - $matchEndMinute;
            } else {
                continue;
            }

            if ($nearestGap === null || $gap < $nearestGap) {
                $nearestGap = $gap;
            }
        }

        return [
            'rest_violated' => $nearestGap !== null && $nearestGap < $minRestMinutes,
            'wait' => $nearestGap === null ? 0 : max(0, $nearestGap - $minRestMinutes),
        ];
    }
}
