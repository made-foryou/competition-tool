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
 * hun beschikbaarheid (opnieuw) moeten indienen.
 */
trait SummarizesAvailability
{
    /**
     * Per deelnemer op welke speeldagen hij beschikbaar is, plus of zijn
     * inzending actueel is (`Competition::isCurrentSubmission()`).
     *
     * `unanswered_match_day_ids` is de ene regel voor "nog niet ingevuld", die
     * matrix, CSV en de alleen-lezenlijst delen: een speeldag is dat als de
     * deelnemer hem niet heeft aangevinkt en óf nog nooit heeft ingediend óf
     * de speeldag na zijn laatste inzending is aangemaakt
     * (`MatchDay::isNewSince()`). Elke andere niet-aangevinkte speeldag is
     * "niet beschikbaar": daar heeft hij bij zijn inzending bewust nee
     * gezegd, ook als die inzending leeg was. De nieuwste speeldag wordt één
     * keer bepaald, dus dit kost geen extra query per deelnemer.
     *
     * @return list<array{id: int, name: string, submitted: bool, match_day_ids: list<int>, unanswered_match_day_ids: list<int>}>
     */
    protected function availabilityRows(Competition $competition): array
    {
        $matchDays = $competition->matchDays()->get(['id', 'created_at']);
        $matchDayIds = $matchDays->pluck('id');
        $latestMatchDayCreatedAt = $competition->latestMatchDayCreatedAt();

        $availableByUser = MatchDayAvailability::query()
            ->whereIn('match_day_id', $matchDayIds)
            ->get()
            ->groupBy('user_id');

        $submittedAtByUser = DB::table('competition_user')
            ->where('competition_id', $competition->id)
            ->pluck('availability_submitted_at', 'user_id');

        return array_values($competition->participants()
            ->orderByRaw('COALESCE(NULLIF(users.nickname, ?), users.name)', [''])
            ->get()
            ->map(function (User $user) use ($availableByUser, $submittedAtByUser, $matchDays, $latestMatchDayCreatedAt): array {
                $submittedAt = Competition::availabilitySubmittedAtFromPivot($submittedAtByUser->get($user->id));
                $matchDayIdsChecked = $availableByUser->get($user->id, collect())->pluck('match_day_id')->all();

                return [
                    'id' => $user->id,
                    'name' => $user->display_name,
                    'submitted' => Competition::isCurrentSubmission($submittedAt, $latestMatchDayCreatedAt),
                    'match_day_ids' => array_values($matchDayIdsChecked),
                    'unanswered_match_day_ids' => array_values($matchDays
                        ->filter(fn (MatchDay $matchDay): bool => ! in_array($matchDay->id, $matchDayIdsChecked, true)
                            && ($submittedAt === null || $matchDay->isNewSince($submittedAt)))
                        ->pluck('id')
                        ->all()),
                ];
            })
            ->all());
    }

    /**
     * De query op de deelnemers van de competitie die (opnieuw) moeten
     * indienen: nooit ingediend, of ingediend vóór de nieuwste speeldag
     * (`Competition::constrainToAwaitingAvailability()`).
     *
     * Apart van `pendingAvailabilityParticipants()` zodat een aanroeper die
     * alleen het aantal nodig heeft `count()` op de query kan doen, in plaats
     * van alle modellen op te halen om ze daarna te tellen.
     *
     * @return BelongsToMany<User, Competition>
     */
    protected function pendingAvailabilityParticipantsQuery(Competition $competition): BelongsToMany
    {
        return Competition::constrainToAwaitingAvailability($competition->participants())
            ->orderByRaw('COALESCE(NULLIF(users.nickname, ?), users.name)', ['']);
    }

    /**
     * De deelnemers van de competitie die (opnieuw) moeten indienen; zie
     * `pendingAvailabilityParticipantsQuery()`.
     *
     * @return Collection<int, User>
     */
    protected function pendingAvailabilityParticipants(Competition $competition): Collection
    {
        return $this->pendingAvailabilityParticipantsQuery($competition)->get();
    }
}
