<?php

namespace App\Http\Controllers;

use App\Concerns\SummarizesMatchDay;
use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\MatchDay;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use SummarizesMatchDay;

    /**
     * Het beheerdersdashboard: de kerncijfers direct, de twee lijsten eronder
     * uitgesteld zodat de pagina meteen rendert.
     */
    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'kpis' => $this->kpis(),
            'upcomingMatchDays' => Inertia::defer(fn (): array => $this->upcomingMatchDays()),
            'recentCompetitions' => Inertia::defer(fn (): array => $this->recentCompetitions()),
        ]);
    }

    /**
     * @return array{competitions: int, active_competitions: int, participants: int, upcoming_match_days: int}
     */
    protected function kpis(): array
    {
        return [
            'competitions' => Competition::query()->count(),
            'active_competitions' => Competition::query()
                ->where('status', CompetitionStatus::Active)
                ->count(),
            'participants' => DB::table('competition_user')->distinct()->count('user_id'),
            'upcoming_match_days' => MatchDay::query()
                ->whereDate('date', '>=', today())
                ->count(),
        ];
    }

    /**
     * De eerstvolgende speeldagen over alle competities heen.
     *
     * @return list<array{id: int, date: string, starts_at: string, ends_at: string, fields_count: int, competition: array{id: int, name: string}}>
     */
    protected function upcomingMatchDays(): array
    {
        return MatchDay::query()
            ->with('competition')
            ->withCount('fields')
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->orderBy('starts_at')
            ->limit(5)
            ->get()
            ->map(fn (MatchDay $matchDay): array => [
                ...$this->matchDayProps($matchDay),
                'fields_count' => (int) $matchDay->fields_count,
                'competition' => [
                    'id' => $matchDay->competition->id,
                    'name' => $matchDay->competition->name,
                ],
            ])
            ->all();
    }

    /**
     * De laatst gestarte competities.
     *
     * @return list<array{id: int, name: string, status: string, participants_count: int}>
     */
    protected function recentCompetitions(): array
    {
        return Competition::query()
            ->withCount('participants')
            ->orderByDesc('starts_at')
            ->limit(5)
            ->get()
            ->map(fn (Competition $competition): array => [
                'id' => $competition->id,
                'name' => $competition->name,
                'status' => $competition->status->value,
                'participants_count' => (int) $competition->participants_count,
            ])
            ->all();
    }
}
