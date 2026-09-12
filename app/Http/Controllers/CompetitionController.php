<?php

namespace App\Http\Controllers;

use App\Concerns\SummarizesMatchDay;
use App\Http\Requests\Competitions\IndexCompetitionRequest;
use App\Http\Requests\Competitions\StoreCompetitionRequest;
use App\Http\Requests\Competitions\UpdateCompetitionRequest;
use App\Models\Competition;
use App\Models\Invitation;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionController extends Controller
{
    use SummarizesMatchDay;

    public function index(IndexCompetitionRequest $request): Response
    {
        ['search' => $search, 'status' => $status] = $request->filters();

        $competitions = Competition::query()
            ->withCount('participants')
            ->when($search, fn (Builder $query, string $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('starts_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Competition $competition): array => [
                ...$this->competitionProps($competition),
                'participants_count' => $competition->participants_count,
            ]);

        return Inertia::render('competitions/index', [
            'competitions' => $competitions,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('competitions/create');
    }

    public function store(StoreCompetitionRequest $request): RedirectResponse
    {
        $competition = Competition::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Competition created.')]);

        return redirect()->route('competitions.edit', $competition);
    }

    public function edit(Competition $competition): Response
    {
        return Inertia::render('competitions/edit', [
            'competition' => $this->competitionProps($competition),
            'participants' => $competition->participants()
                ->orderByRaw('COALESCE(NULLIF(users.nickname, ?), users.name)', [''])
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'is_admin' => $user->isAdmin(),
                ])
                ->all(),
            'availability' => $this->availabilityRows($competition),
            'matchDays' => $competition->matchDays()
                ->withCount('fields')
                ->get()
                ->map(fn (MatchDay $matchDay): array => [
                    ...$this->matchDayProps($matchDay),
                    'fields_count' => $matchDay->fields_count,
                ])
                ->all(),
            'pendingInvitations' => $competition->invitations()
                ->pending()
                ->get()
                ->map(fn (Invitation $invitation): array => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'expires_at' => $invitation->expires_at->toDateString(),
                ])
                ->all(),
        ]);
    }

    public function update(UpdateCompetitionRequest $request, Competition $competition): RedirectResponse
    {
        $competition->update($request->validated());

        return redirect()->route('competitions.edit', $competition);
    }

    public function destroy(Competition $competition): RedirectResponse
    {
        $competition->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Competition deleted.')]);

        return redirect()->route('competitions.index');
    }

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
     * @return array<string, mixed>
     */
    protected function competitionProps(Competition $competition): array
    {
        return [
            'id' => $competition->id,
            'name' => $competition->name,
            'slug' => $competition->slug,
            'description' => $competition->description,
            'location' => $competition->location,
            'starts_at' => $competition->starts_at->toDateString(),
            'ends_at' => $competition->ends_at?->toDateString(),
            'status' => $competition->status->value,
        ];
    }
}
