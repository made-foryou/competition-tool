<?php

namespace App\Concerns;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Levert de wedstrijden van één deelnemer binnen één competitie vanuit zijn
 * eigen perspectief: tegenstander, eigen score vooraan, winst of verlies, en
 * een door de server bepaalde staat zodat het scherm zelf niets afleidt.
 *
 * Alleen eigen wedstrijden: het speelschema van anderen is bewust geen
 * onderdeel (besluit bij #59). Planningsdetails voor beheer, zoals
 * vastzetten of de reden van niet-inplannen, ontbreken om dezelfde reden.
 *
 * De staten: `upcoming` (ingepland, speeldag vandaag of later),
 * `awaiting_result` (ingepland, speeldag voorbij, nog geen uitslag),
 * `unscheduled` (nog geen plek), `not_played` (openstaand op een afgeronde
 * competitie) en `played`.
 */
trait SummarizesParticipantMatches
{
    /**
     * Volgorde van de staten: eerst wat nog gespeeld moet worden, dan de rest.
     */
    private const array PARTICIPANT_MATCH_STATE_ORDER = [
        'upcoming' => 0,
        'awaiting_result' => 1,
        'unscheduled' => 2,
        'not_played' => 3,
        'played' => 4,
    ];

    /**
     * Alle wedstrijden van de deelnemer in deze competitie, gesorteerd op
     * staat en moment. De scores staan vanuit de deelnemer: `own_score` is
     * zijn eigen score, ongeacht in welke kolom die is opgeslagen.
     *
     * @return list<array{
     *     id: int,
     *     opponent: string,
     *     opponent_is_participant: bool,
     *     state: 'upcoming'|'awaiting_result'|'unscheduled'|'not_played'|'played',
     *     own_score: int|null,
     *     opponent_score: int|null,
     *     outcome: 'won'|'lost'|null,
     *     match_day_date: string|null,
     *     field_name: string|null,
     *     starts_at: string|null,
     *     ends_at: string|null,
     * }>
     */
    protected function participantMatchRows(User $user, Competition $competition): array
    {
        /** @var list<int> $participantIds */
        $participantIds = $competition->participants()->pluck('users.id')->all();
        $today = today();

        $rows = $competition->matches()
            ->where(fn (Builder $query) => $query
                ->where('first_player_id', $user->id)
                ->orWhere('second_player_id', $user->id))
            ->with(['firstPlayer', 'secondPlayer', 'matchDay', 'matchDayField'])
            ->get()
            ->map(function (CompetitionMatch $match) use ($user, $competition, $participantIds, $today): array {
                $isFirstPlayer = $match->first_player_id === $user->id;
                $opponent = $isFirstPlayer ? $match->secondPlayer : $match->firstPlayer;
                $ownScore = $match->isPlayed() ? ($isFirstPlayer ? $match->first_player_score : $match->second_player_score) : null;
                $opponentScore = $match->isPlayed() ? ($isFirstPlayer ? $match->second_player_score : $match->first_player_score) : null;

                return [
                    'id' => $match->id,
                    'opponent' => $opponent->display_name,
                    'opponent_is_participant' => in_array($opponent->id, $participantIds, true),
                    'state' => $this->participantMatchState($match, $competition, $today),
                    'own_score' => $ownScore,
                    'opponent_score' => $opponentScore,
                    'outcome' => match (true) {
                        $ownScore === null || $opponentScore === null => null,
                        $ownScore > $opponentScore => 'won',
                        $ownScore < $opponentScore => 'lost',
                        default => null,
                    },
                    'match_day_date' => $match->matchDay?->date->toDateString(),
                    'field_name' => $match->matchDayField?->name,
                    'starts_at' => $match->starts_at,
                    'ends_at' => $match->ends_at,
                ];
            })
            ->all();

        usort($rows, $this->compareParticipantMatchRows(...));

        return $rows;
    }

    /**
     * Samenvatting voor het dashboard: de eerste drie openstaande wedstrijden
     * (upcoming, awaiting_result, unscheduled, in die volgorde) plus tellers.
     *
     * @return array{
     *     next_matches: list<array{
     *         id: int,
     *         opponent: string,
     *         opponent_is_participant: bool,
     *         state: 'upcoming'|'awaiting_result'|'unscheduled'|'not_played'|'played',
     *         own_score: int|null,
     *         opponent_score: int|null,
     *         outcome: 'won'|'lost'|null,
     *         match_day_date: string|null,
     *         field_name: string|null,
     *         starts_at: string|null,
     *         ends_at: string|null,
     *     }>,
     *     total_count: int,
     *     open_count: int,
     *     unscheduled_count: int,
     *     played_count: int,
     *     won_count: int,
     *     lost_count: int,
     * }
     */
    protected function participantMatchOverview(User $user, Competition $competition): array
    {
        $rows = $this->participantMatchRows($user, $competition);

        $open = array_values(array_filter(
            $rows,
            fn (array $row): bool => in_array($row['state'], ['upcoming', 'awaiting_result', 'unscheduled'], true),
        ));

        $countWhere = fn (callable $callback): int => count(array_filter($rows, $callback));

        return [
            'next_matches' => array_slice($open, 0, 3),
            'total_count' => count($rows),
            'open_count' => count($open),
            'unscheduled_count' => $countWhere(fn (array $row): bool => $row['state'] === 'unscheduled'),
            'played_count' => $countWhere(fn (array $row): bool => $row['state'] === 'played'),
            'won_count' => $countWhere(fn (array $row): bool => $row['outcome'] === 'won'),
            'lost_count' => $countWhere(fn (array $row): bool => $row['outcome'] === 'lost'),
        ];
    }

    /**
     * @return 'upcoming'|'awaiting_result'|'unscheduled'|'not_played'|'played'
     */
    private function participantMatchState(CompetitionMatch $match, Competition $competition, CarbonInterface $today): string
    {
        return match (true) {
            $match->isPlayed() => 'played',
            $competition->status === CompetitionStatus::Finished => 'not_played',
            ! $match->isScheduled() => 'unscheduled',
            $match->matchDay?->date->lt($today) === true => 'awaiting_result',
            default => 'upcoming',
        };
    }

    /**
     * Eerst op staat; binnen een staat zonder speeldag achteraan; dan op
     * speeldag en begintijd (gespeeld: nieuwste eerst, anders oplopend); en
     * ten slotte op tegenstander en id.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function compareParticipantMatchRows(array $a, array $b): int
    {
        $byState = self::PARTICIPANT_MATCH_STATE_ORDER[$a['state']] <=> self::PARTICIPANT_MATCH_STATE_ORDER[$b['state']];
        if ($byState !== 0) {
            return $byState;
        }

        $byPlanned = ($a['match_day_date'] === null) <=> ($b['match_day_date'] === null);
        if ($byPlanned !== 0) {
            return $byPlanned;
        }

        $byMoment = [$a['match_day_date'], $a['starts_at']] <=> [$b['match_day_date'], $b['starts_at']];
        if ($a['state'] === 'played') {
            $byMoment = -$byMoment;
        }
        if ($byMoment !== 0) {
            return $byMoment;
        }

        return [mb_strtolower($a['opponent']), $a['id']] <=> [mb_strtolower($b['opponent']), $b['id']];
    }
}
