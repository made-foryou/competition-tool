<?php

namespace App\Http\Controllers;

use App\Actions\Competitions\UpdateMatchDay;
use App\Concerns\SummarizesAvailability;
use App\Concerns\SummarizesMatchDay;
use App\Http\Requests\Competitions\StoreMatchDayRequest;
use App\Http\Requests\Competitions\UpdateMatchDayRequest;
use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\MatchDayField;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MatchDayController extends Controller
{
    use SummarizesAvailability;
    use SummarizesMatchDay;

    /**
     * Een nieuwe (of naar een andere datum verzette) speeldag maakt iedereen
     * met een oudere inzending weer pending, zonder iets op te slaan:
     * `Competition::constrainToAwaitingAvailability()` vergelijkt het
     * inzendmoment met het nieuwste beschikbaarheidsverzoek
     * (`availability_requested_at`). Anders leest de planner "geen rij" als
     * "niet beschikbaar" op de nieuwe dag. Verwijderen van die speeldag
     * herstelt de stand vanzelf.
     *
     * De toast zegt wie nu nog moet indienen (`availabilityRequestMessage()`).
     */
    public function store(StoreMatchDayRequest $request, Competition $competition): RedirectResponse
    {
        DB::transaction(function () use ($request, $competition): void {
            /** @var MatchDay $matchDay */
            $matchDay = $competition->matchDays()->create($request->safe()->except('field_count'));

            $matchDay->fields()->createMany(
                collect(range(1, $request->integer('field_count')))
                    ->map(fn (int $position): array => [
                        'name' => __('Field :number', ['number' => $position]),
                        'position' => $position,
                    ])
                    ->all(),
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->availabilityRequestMessage($competition, $request->user(), moved: false),
        ]);

        return back();
    }

    /**
     * `moveImpact` is wat er vervalt als de beheerder de datum verzet
     * (`UpdateMatchDay`): alle beschikbaarheidsrijen van deze speeldag en de
     * openstaande wedstrijden die erop ingepland staan, waarvan een deel
     * vastgezet. Gespeelde wedstrijden schuiven mee en tellen dus niet.
     */
    public function edit(Competition $competition, MatchDay $matchDay): Response
    {
        return Inertia::render('competitions/match-days/edit', [
            'competition' => [
                'id' => $competition->id,
                'name' => $competition->name,
                'starts_at' => $competition->starts_at->toDateString(),
                'ends_at' => $competition->ends_at?->toDateString(),
            ],
            'matchDay' => $this->matchDayProps($matchDay),
            'fields' => $matchDay->fields()
                ->get()
                ->map(fn (MatchDayField $field): array => [
                    'id' => $field->id,
                    'name' => $field->name,
                    'position' => $field->position,
                ])
                ->all(),
            'moveImpact' => [
                'available_count' => MatchDayAvailability::query()->where('match_day_id', $matchDay->id)->count(),
                'scheduled_match_count' => $matchDay->pendingMatches()->count(),
                'pinned_match_count' => $matchDay->pendingMatches()->whereNotNull('pinned_at')->count(),
            ],
        ]);
    }

    /**
     * Slaat datum en tijden op via `UpdateMatchDay`. Blijft de datum gelijk,
     * dan raakt dat beschikbaarheid en planning niet en volgt de gewone toast.
     *
     * Is de speeldag verzet, dan zijn de antwoorden voor die dag vervallen en
     * wordt iedereen opnieuw om beschikbaarheid gevraagd, net als bij een
     * nieuwe speeldag. De toast heeft daarom dezelfde opbouw als bij
     * `store()`, met erachter hoeveel ingeplande wedstrijden uit de planning
     * zijn gehaald.
     */
    public function update(UpdateMatchDayRequest $request, Competition $competition, MatchDay $matchDay, UpdateMatchDay $updateMatchDay): RedirectResponse
    {
        $releasedMatchCount = $updateMatchDay->handle($matchDay, $request->matchDayAttributes());

        $message = $releasedMatchCount === null
            ? __('Match day updated.')
            : $this->withReleased(
                $this->availabilityRequestMessage($competition, $request->user(), moved: true),
                $releasedMatchCount,
            );

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function destroy(Competition $competition, MatchDay $matchDay): RedirectResponse
    {
        $matchDay->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Match day removed.')]);

        return redirect()->route('competitions.edit', $competition);
    }

    /**
     * De toast na het toevoegen of verzetten van een speeldag. Beide vragen
     * iedereen opnieuw om beschikbaarheid, dus de opbouw is gelijk; alleen
     * het werkwoord verschilt.
     *
     * De toast telt wie nu moet indienen, niet wie er net pending werd: bij een
     * tweede speeldag direct na de eerste staat iedereen al open. De ingelogde
     * beheerder telt niet mee; als hij zelf deelnemer is stuurt
     * `EnsureAvailabilityIsSubmitted` hem direct naar het formulier. Op een
     * niet-actieve competitie dwingt niets af, dus daar volgt een toast die
     * zegt dat de vraag pas bij activeren speelt.
     *
     * Losse sleutelparen in plaats van trans_choice: dat is de conventie in
     * dit project (zie CompetitionAvailabilityReminderController).
     */
    private function availabilityRequestMessage(Competition $competition, User $admin, bool $moved): string
    {
        if (! $competition->status->allowsAvailabilityChanges()) {
            return $moved
                ? __('Match day moved. Participants submit their availability once the competition is active.')
                : __('Match day added. Participants submit their availability once the competition is active.');
        }

        $pendingCount = $this->pendingAvailabilityParticipantsQuery($competition)
            ->whereKeyNot($admin->id)
            ->count();

        $replace = ['count' => $pendingCount];

        return match (true) {
            $pendingCount === 0 => $moved
                ? __('Match day moved.')
                : __('Match day added.'),
            $pendingCount === 1 => $moved
                ? __('Match day moved. :count participant still has to submit their availability.', $replace)
                : __('Match day added. :count participant still has to submit their availability.', $replace),
            default => $moved
                ? __('Match day moved. :count participants still have to submit their availability.', $replace)
                : __('Match day added. :count participants still have to submit their availability.', $replace),
        };
    }

    /**
     * Plakt de zin over de wedstrijden die uit de planning gingen achter een
     * melding, of laat hem ongemoeid als er geen wedstrijd ingepland stond.
     */
    private function withReleased(string $message, int $releasedMatchCount): string
    {
        if ($releasedMatchCount < 1) {
            return $message;
        }

        return $message.' '.($releasedMatchCount === 1
            ? __(':count scheduled match was taken off the schedule.', ['count' => $releasedMatchCount])
            : __(':count scheduled matches were taken off the schedule.', ['count' => $releasedMatchCount]));
    }
}
