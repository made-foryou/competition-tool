<?php

namespace App\Concerns;

use App\Models\Competition;
use App\Models\MatchDayAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Levert de beschikbaarheidsmatrix van een competitie en de deelnemers die
 * hun beschikbaarheid nog niet hebben ingediend.
 */
trait SummarizesAvailability
{
    /**
     * Per deelnemer op welke speeldagen hij beschikbaar is, plus of hij het
     * formulier uberhaupt al heeft ingediend.
     *
     * @return list<array{id: int, name: string, submitted: bool, match_day_ids: list<int>}>
     */
    protected function availabilityRows(Competition $competition): array
    {
        $matchDayIds = $competition->matchDays()->pluck('id');

        $availableByUser = MatchDayAvailability::query()
            ->whereIn('match_day_id', $matchDayIds)
            ->get()
            ->groupBy('user_id');

        $submittedUserIds = DB::table('competition_user')
            ->where('competition_id', $competition->id)
            ->whereNotNull('availability_submitted_at')
            ->pluck('user_id')
            ->all();

        return array_values($competition->participants()
            ->orderByRaw('COALESCE(NULLIF(users.nickname, ?), users.name)', [''])
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->display_name,
                'submitted' => in_array($user->id, $submittedUserIds, true),
                'match_day_ids' => array_values($availableByUser->get($user->id, collect())
                    ->pluck('match_day_id')
                    ->all()),
            ])
            ->all());
    }

    /**
     * De deelnemers van de competitie die hun beschikbaarheid nog niet
     * hebben ingediend (geen `availability_submitted_at` op de koppeltabel).
     *
     * @return Collection<int, User>
     */
    protected function pendingAvailabilityParticipants(Competition $competition): Collection
    {
        return $competition->participants()
            ->wherePivotNull('availability_submitted_at')
            ->orderByRaw('COALESCE(NULLIF(users.nickname, ?), users.name)', [''])
            ->get();
    }
}
