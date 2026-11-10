<?php

namespace App\Concerns;

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Legt vast op welke speeldagen een deelnemer aanwezig is. Een rij in
 * match_day_availabilities betekent aanwezig; afwezigheid wordt niet apart
 * opgeslagen. Omdat een lege selectie dus nul rijen oplevert, markeert
 * availability_submitted_at op de koppeltabel dat de deelnemer het formulier
 * daadwerkelijk heeft ingediend. Die kolom is altijd het moment van de laatste
 * inzending en wordt nooit geleegd. Actueel is een inzending die niet ouder is
 * dan de nieuwste speeldag (`Competition::isCurrentSubmission()`).
 */
trait SyncsAvailability
{
    /**
     * @param  list<int>  $matchDayIds
     */
    protected function syncAvailability(User $user, Competition $competition, array $matchDayIds): void
    {
        DB::transaction(function () use ($user, $competition, $matchDayIds): void {
            $competitionMatchDayIds = $competition->matchDays()->pluck('id');

            MatchDayAvailability::query()
                ->where('user_id', $user->id)
                ->whereIn('match_day_id', $competitionMatchDayIds)
                ->delete();

            $selected = array_values($competitionMatchDayIds
                ->intersect($matchDayIds)
                ->map(fn (int $matchDayId): array => [
                    'user_id' => $user->id,
                    'match_day_id' => $matchDayId,
                ])
                ->all());

            if ($selected !== []) {
                MatchDayAvailability::query()->insert($this->timestamped($selected));
            }

            $user->competitions()->updateExistingPivot($competition->id, [
                'availability_submitted_at' => now(),
            ]);
        });
    }

    /**
     * De speeldagen van deze competitie met de aanwezigheid van de deelnemer.
     *
     * `is_new` volgt uit zijn inzendmoment: een speeldag die ná zijn laatste
     * inzending is aangemaakt, is nieuw voor hem (`MatchDay::isNewSince()`),
     * ook als die inzending leeg was. Zonder inzending is niets nieuw.
     *
     * @return list<array{id: int, date: string, starts_at: string, ends_at: string, is_available: bool, is_new: bool}>
     */
    protected function availabilityProps(User $user, Competition $competition): array
    {
        $available = MatchDayAvailability::query()
            ->where('user_id', $user->id)
            ->pluck('match_day_id')
            ->all();

        $submittedAt = $user->availabilitySubmittedAtFor($competition);

        return array_values($competition->matchDays()
            ->get()
            ->map(fn (MatchDay $matchDay): array => [
                'id' => $matchDay->id,
                'date' => $matchDay->date->toDateString(),
                'starts_at' => $matchDay->starts_at,
                'ends_at' => $matchDay->ends_at,
                'is_available' => in_array($matchDay->id, $available, true),
                'is_new' => $matchDay->isNewSince($submittedAt),
            ])
            ->all());
    }

    /**
     * `insert()` vult geen timestamps, dus die zetten we zelf.
     *
     * @param  list<array{user_id: int, match_day_id: int}>  $rows
     * @return list<array<string, mixed>>
     */
    private function timestamped(array $rows): array
    {
        $now = now();

        return array_map(
            fn (array $row): array => [...$row, 'created_at' => $now, 'updated_at' => $now],
            $rows,
        );
    }
}
