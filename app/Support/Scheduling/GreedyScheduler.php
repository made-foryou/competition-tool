<?php

namespace App\Support\Scheduling;

use App\Enums\PlacementViolation;
use App\Enums\SchedulingFailure;
use LogicException;

/**
 * Het greedy toewijzingsalgoritme: de meest beperkte wedstrijden eerst, per
 * wedstrijd de best scorende plek. Volledig deterministisch — geen
 * randomisatie, alle invoer in een vaste volgorde en bij gelijke score wint
 * de eerste kandidaat (vroegste dag, vroegste slot, laagste tafel).
 */
final readonly class GreedyScheduler
{
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
     * De score van een plek als lexicografisch te vergelijken drietal.
     *
     * Bewust geen gewogen som: een penalty per rustschending moet dan groter
     * zijn dan élk denkbaar verschil in eerlijkheid plus wachttijd, en dat
     * houdt geen stand. Twaalf gecombineerde wedstrijden op een drukke avond
     * tikken een eerlijkheidsgewicht zo ver op dat de som liever één
     * rustschending op een rustige avond koopt — precies andersom dan het
     * ontwerp voorschrijft. Lexicografisch vergelijken maakt "rust eerst" een
     * eigenschap in plaats van een aanname.
     *
     * @throws LogicException als de speeldag niet in de context zit; een
     *                        score van nul teruggeven zou dan stilzwijgend de
     *                        winnende kandidaat opleveren
     */
    public function score(Placement $placement, int $firstPlayerId, int $secondPlayerId, SchedulingContext $context): PlacementScore
    {
        $matchDay = $context->matchDay($placement->matchDayId);

        if (! $matchDay instanceof MatchDaySchedule) {
            throw new LogicException('Cannot score a placement on match day '.$placement->matchDayId.': it is not part of the scheduling context.');
        }

        return $this->scoreOn($matchDay, $placement->slot, $firstPlayerId, $secondPlayerId, $context);
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
        $bestMatchDay = null;

        /** @var array<string, PlacementViolation> $violations */
        $violations = [];

        // Over de speeldagen van de context lopen in plaats van over de ids:
        // de volgorde is dezelfde (de gedeelde ids komen uit de context) en de
        // speeldag is hier per definitie bekend.
        foreach ($context->matchDays as $matchDay) {
            if (! in_array($matchDay->matchDayId, $sharedMatchDayIds, true)) {
                continue;
            }

            foreach ($matchDay->grid->slots as $slot) {
                foreach ($matchDay->fieldIds as $fieldId) {
                    $placement = new Placement($matchDay->matchDayId, $fieldId, $slot);
                    $violation = $this->validator->violation($placement, $match->firstPlayerId, $match->secondPlayerId, $context);

                    if ($violation instanceof PlacementViolation) {
                        $violations[$violation->value] = $violation;

                        continue;
                    }

                    $score = $this->scoreOn($matchDay, $slot, $match->firstPlayerId, $match->secondPlayerId, $context);

                    if ($bestScore === null || $score->isBetterThan($bestScore)) {
                        $best = $placement;
                        $bestScore = $score;
                        $bestMatchDay = $matchDay;
                    }
                }
            }
        }

        if ($best === null || $bestScore === null || $bestMatchDay === null) {
            $solution->fail($match->id, $this->diagnose($sharedMatchDayIds, $violations));

            return;
        }

        $matchEndMinute = $bestMatchDay->grid->matchEndMinute($best->slot);

        $context->board->occupyTable($best->matchDayId, $best->fieldId, $best->slot->startMinute, $best->slot->endMinute);
        $context->board->occupyPlayer($match->firstPlayerId, $best->matchDayId, $best->slot->startMinute, $matchEndMinute);
        $context->board->occupyPlayer($match->secondPlayerId, $best->matchDayId, $best->slot->startMinute, $matchEndMinute);

        $solution->place($match->id, $best, $bestScore->restViolated);
    }

    /**
     * De score van een slot op een al opgehaalde speeldag.
     */
    private function scoreOn(MatchDaySchedule $matchDay, Slot $slot, int $firstPlayerId, int $secondPlayerId, SchedulingContext $context): PlacementScore
    {
        $startMinute = $slot->startMinute;
        $matchEndMinute = $matchDay->grid->matchEndMinute($slot);
        $minRestMinutes = $context->settings->minRestMinutes;

        $first = $this->rate($firstPlayerId, $matchDay->matchDayId, $startMinute, $matchEndMinute, $minRestMinutes, $context->board);
        $second = $this->rate($secondPlayerId, $matchDay->matchDayId, $startMinute, $matchEndMinute, $minRestMinutes, $context->board);

        return new PlacementScore(
            restViolations: (int) $first['rest_violated'] + (int) $second['rest_violated'],
            fairness: $context->board->matchesOn($firstPlayerId, $matchDay->matchDayId)
                + $context->board->matchesOn($secondPlayerId, $matchDay->matchDayId),
            waitMinutes: $first['wait'] + $second['wait'],
        );
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
