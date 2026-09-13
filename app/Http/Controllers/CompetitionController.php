<?php

namespace App\Http\Controllers;

use App\Actions\Competitions\SyncCompetitionMatches;
use App\Concerns\SummarizesMatchDay;
use App\Http\Requests\Competitions\IndexCompetitionRequest;
use App\Http\Requests\Competitions\StoreCompetitionRequest;
use App\Http\Requests\Competitions\UpdateCompetitionRequest;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\Invitation;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;
use App\Support\CompetitionSettings;
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
            ->when($search, fn (Builder $query, string $search) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
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
                    'display_name' => $user->display_name,
                    'email' => $user->email,
                    'is_admin' => $user->isAdmin(),
                ])
                ->all(),
            'availability' => $this->availabilityRows($competition),
            'matches' => Inertia::defer(fn (): array => $this->matchRows($competition)),
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
            'settings' => $competition->settings->toArray(),
            'settingsLimits' => CompetitionSettings::limits(),
        ]);
    }

    public function update(UpdateCompetitionRequest $request, Competition $competition, SyncCompetitionMatches $syncCompetitionMatches): RedirectResponse
    {
        $competition->update($request->validated());

        // Het type bepaalt hoe de wedstrijdenlijst wordt opgebouwd, dus een
        // typewijziging moet de lijst hersynchroniseren. Met één type is dit
        // nog latent (wasChanged('type') kan niet true worden), maar zodra er
        // een tweede type bijkomt herbouwt deze aanroep de lijst automatisch.
        if ($competition->wasChanged('type')) {
            $syncCompetitionMatches->handle($competition);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Competition updated.')]);

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
     * De wedstrijdenlijst van de competitie, canoniek geordend op id. Wordt
     * automatisch gesynchroniseerd met de deelnemerslijst; er is dus geen
     * generate-actie voor deze props. De is_participant-vlaggen laten de UI
     * spelers markeren die inmiddels uit de competitie zijn vertrokken (hun
     * gespeelde wedstrijden blijven immers staan).
     *
     * @return list<array{id: int, first_player: string, first_player_is_participant: bool, second_player: string, second_player_is_participant: bool, status: string}>
     */
    protected function matchRows(Competition $competition): array
    {
        /** @var list<int> $participantIds */
        $participantIds = $competition->participants()->pluck('users.id')->all();

        return array_values($competition->matches()
            ->with(['firstPlayer', 'secondPlayer'])
            ->get()
            ->map(fn (CompetitionMatch $match): array => [
                'id' => $match->id,
                'first_player' => $match->firstPlayer->display_name,
                'first_player_is_participant' => in_array($match->first_player_id, $participantIds, true),
                'second_player' => $match->secondPlayer->display_name,
                'second_player_is_participant' => in_array($match->second_player_id, $participantIds, true),
                'status' => $match->status->value,
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
            'type' => $competition->type->value,
        ];
    }
}
