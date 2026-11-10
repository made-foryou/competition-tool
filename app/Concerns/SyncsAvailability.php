<?php

namespace App\Concerns;

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Legt vast op welke speeldagen een deelnemer aanwezig is. Een rij in
 * match_day_availabilities betekent aanwezig; afwezigheid wordt niet apart
 * opgeslagen. Omdat een lege selectie dus nul rijen oplevert, markeert
 * availability_submitted_at op de koppeltabel dat de deelnemer het formulier
 * daadwerkelijk heeft ingediend. Dat moment betekent "ingediend voor de
 * huidige speeldagen": `Competition::reopenAvailability()` leegt het zodra er
 * een speeldag bijkomt.
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
     * `is_new` is afgeleid zonder extra kolom: `syncAvailability()` verwijdert
     * bij elke opslag alle rijen van de deelnemer en schrijft ze opnieuw met
     * `created_at = now()`. De nieuwste `created_at` is dus het moment van zijn
     * laatste inzending met vinkjes. Een speeldag die daarna is aangemaakt is
     * nieuw voor hem. Zonder rijen is niets nieuw.
     *
     * @return list<array{id: int, date: string, starts_at: string, ends_at: string, is_available: bool, is_new: bool}>
     */
    protected function availabilityProps(User $user, Competition $competition): array
    {
        $available = MatchDayAvailability::query()
            ->where('user_id', $user->id)
            ->pluck('match_day_id')
            ->all();

        $matchDays = $competition->matchDays()->get();

        $lastAnsweredAt = MatchDayAvailability::query()
            ->where('user_id', $user->id)
            ->whereIn('match_day_id', $matchDays->pluck('id'))
            ->max('created_at');

        $lastAnsweredAt = $lastAnsweredAt === null ? null : Carbon::parse($lastAnsweredAt);

        return array_values($matchDays
            ->map(fn (MatchDay $matchDay): array => [
                'id' => $matchDay->id,
                'date' => $matchDay->date->toDateString(),
                'starts_at' => $matchDay->starts_at,
                'ends_at' => $matchDay->ends_at,
                'is_available' => in_array($matchDay->id, $available, true),
                'is_new' => $matchDay->isNewSince($lastAnsweredAt),
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
