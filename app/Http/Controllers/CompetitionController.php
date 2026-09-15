<?php

namespace App\Http\Controllers;

use App\Actions\Competitions\SyncCompetitionMatches;
use App\Concerns\SummarizesAvailability;
use App\Concerns\SummarizesMatchDay;
use App\Enums\CompetitionStatus;
use App\Http\Requests\Competitions\IndexCompetitionRequest;
use App\Http\Requests\Competitions\StoreCompetitionRequest;
use App\Http\Requests\Competitions\UpdateCompetitionRequest;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\Invitation;
use App\Models\MatchDay;
use App\Models\User;
use App\Support\CompetitionSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionController extends Controller
{
    use SummarizesAvailability, SummarizesMatchDay;

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

    /**
     * Elke nog niet geaccepteerde uitnodiging, dus ook de verlopen en
     * afgewezen exemplaren: die verdwenen eerder stilzwijgend uit beeld,
     * inclusief de knoppen om ze in te trekken of opnieuw te versturen.
     *
     * @return list<array<string, mixed>>
     */
    protected function invitationRows(Competition $competition): array
    {
        $invitations = $competition->invitations()
            ->whereNull('accepted_at')
            ->orderBy('expires_at')
            ->orderBy('id')
            ->get();

        $emailsWithAccount = User::query()
            ->whereIn('email', $invitations->pluck('email'))
            ->pluck('email')
            ->all();

        return array_values($invitations
            ->map(fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at->toDateString(),
                'is_expired' => $invitation->isExpired(),
                'is_declined' => $invitation->isDeclined(),
                'has_account' => in_array($invitation->email, $emailsWithAccount, true),
            ])
            ->all());
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
            'availabilityReminder' => $this->availabilityReminderProps($competition),
            'matches' => Inertia::defer(fn (): array => $this->matchRows($competition)),
            'matchDays' => $competition->matchDays()
                ->withCount('fields')
                ->get()
                ->map(fn (MatchDay $matchDay): array => [
                    ...$this->matchDayProps($matchDay),
                    'fields_count' => $matchDay->fields_count,
                ])
                ->all(),
            'pendingInvitations' => $this->invitationRows($competition),
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
     * De staat van de beschikbaarheidsherinnering voor de beheerder: hoeveel
     * deelnemers nog moeten invullen, of er nu verstuurd mag worden en zo niet,
     * waarom niet.
     *
     * De server bepaalt de reden, zodat het scherm geen eigen afleiding hoeft
     * te maken die in een andere volgorde uitkomt dan de server. `blocked_reason`
     * volgt daarom exact de volgorde van de guards in
     * `CompetitionAvailabilityReminderController`: eerst de status, dan de
     * speeldagen, dan het venster van 24 uur, dan de openstaande deelnemers.
     * Wijkt die volgorde hier af, dan toont het scherm een andere reden dan de
     * melding die de beheerder krijgt zodra hij op de knop drukt.
     *
     * `available_at` is alleen gevuld bij `'window'` en is bewust een
     * ISO-string en geen kant-en-klare zin: de frontend formatteert het moment
     * zelf in de tijdzone van de beheerder. De server heeft geen tijdzone van
     * de beheerder en zou hier UTC tonen.
     *
     * @return array{pending_count: int, can_send: bool, available_at: string|null, blocked_reason: 'inactive'|'no_match_days'|'window'|'none_pending'|null}
     */
    protected function availabilityReminderProps(Competition $competition): array
    {
        $pendingCount = $this->pendingAvailabilityParticipantsQuery($competition)->count();
        $availableAt = $competition->availabilityReminderAvailableAt();

        $blockedReason = match (true) {
            $competition->status !== CompetitionStatus::Active => 'inactive',
            $competition->matchDays()->exists() === false => 'no_match_days',
            $competition->availabilityReminderWindowIsOpen() === false => 'window',
            $pendingCount === 0 => 'none_pending',
            default => null,
        };

        return [
            'pending_count' => $pendingCount,
            'can_send' => $blockedReason === null,
            'available_at' => $availableAt?->toIso8601String(),
            'blocked_reason' => $blockedReason,
        ];
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
            // Of de deelnemerslinks nog ergens toe leiden. Beide regels staan
            // op de enum, zodat het scherm dezelfde grens hanteert als de
            // routes zelf: inschrijven kan alleen op een actieve competitie
            // en een concept-competitie geeft deelnemers een 404.
            'allows_sign_up' => $competition->status->allowsSignUp(),
            'is_visible_to_participants' => $competition->status->isVisibleToParticipants(),
        ];
    }
}
