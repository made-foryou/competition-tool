<?php

namespace App\Concerns;

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
     * `unanswered_match_day_ids` is de ene regel voor "nog niet ingevuld", die
     * matrix, CSV en de alleen-lezenlijst delen: een speeldag is dat als de
     * deelnemer hem niet heeft aangevinkt, niet heeft ingediend en óf geen
     * enkel antwoord in deze competitie heeft óf de speeldag heeft gekregen na
     * zijn laatste antwoord (`MatchDay::isNewSince()`). Elke andere niet
     * aangevinkte speeldag is "niet beschikbaar". Het laatste antwoord volgt
     * uit de al opgehaalde rijen, dus dit kost geen extra query per deelnemer.
     *
     * @return list<array{id: int, name: string, submitted: bool, match_day_ids: list<int>, unanswered_match_day_ids: list<int>}>
     */
    protected function availabilityRows(Competition $competition): array
    {
        $matchDays = $competition->matchDays()->get(['id', 'created_at']);
        $matchDayIds = $matchDays->pluck('id');

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
            ->map(function (User $user) use ($availableByUser, $submittedUserIds, $matchDays): array {
                $answers = $availableByUser->get($user->id, collect());
                $submitted = in_array($user->id, $submittedUserIds, true);
                $matchDayIdsChecked = $answers->pluck('match_day_id')->all();
                $lastAnsweredAt = $answers->max('created_at');

                return [
                    'id' => $user->id,
                    'name' => $user->display_name,
                    'submitted' => $submitted,
                    'match_day_ids' => array_values($matchDayIdsChecked),
                    'unanswered_match_day_ids' => $submitted ? [] : array_values($matchDays
                        ->filter(fn (MatchDay $matchDay): bool => ! in_array($matchDay->id, $matchDayIdsChecked, true)
                            && ($answers->isEmpty() || $matchDay->isNewSince($lastAnsweredAt)))
                        ->pluck('id')
                        ->all()),
                ];
            })
            ->all());
    }

    /**
     * De query op de deelnemers van de competitie die hun beschikbaarheid nog
     * niet hebben ingediend (geen `availability_submitted_at` op de
     * koppeltabel).
     *
     * Apart van `pendingAvailabilityParticipants()` zodat een aanroeper die
     * alleen het aantal nodig heeft `count()` op de query kan doen, in plaats
     * van alle modellen op te halen om ze daarna te tellen.
     *
     * @return BelongsToMany<User, Competition>
     */
    protected function pendingAvailabilityParticipantsQuery(Competition $competition): BelongsToMany
    {
        return $competition->participants()
            ->wherePivotNull('availability_submitted_at')
            ->orderByRaw('COALESCE(NULLIF(users.nickname, ?), users.name)', ['']);
    }

    /**
     * De deelnemers van de competitie die hun beschikbaarheid nog niet
     * hebben ingediend (geen `availability_submitted_at` op de koppeltabel).
     *
     * @return Collection<int, User>
     */
    protected function pendingAvailabilityParticipants(Competition $competition): Collection
    {
        return $this->pendingAvailabilityParticipantsQuery($competition)->get();
    }
}
