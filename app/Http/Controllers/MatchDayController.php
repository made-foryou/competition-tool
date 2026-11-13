<?php

namespace App\Http\Controllers;

use App\Concerns\SummarizesAvailability;
use App\Concerns\SummarizesMatchDay;
use App\Http\Requests\Competitions\StoreMatchDayRequest;
use App\Http\Requests\Competitions\UpdateMatchDayRequest;
use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MatchDayController extends Controller
{
    use SummarizesAvailability;
    use SummarizesMatchDay;

    /**
     * Een nieuwe (of naar een andere datum verzette) speeldag maakt iedereen met een oudere inzending weer
     * pending, zonder iets op te slaan:
     * `Competition::constrainToAwaitingAvailability()` vergelijkt het
     * inzendmoment met het nieuwste beschikbaarheidsverzoek (`availability_requested_at`). Anders leest de planner "geen
     * rij" als "niet beschikbaar" op de nieuwe dag. Verwijderen van die
     * speeldag herstelt de stand vanzelf.
     *
     * De toast telt wie nu moet indienen, niet wie er net pending werd: bij een
     * tweede speeldag direct na de eerste staat iedereen al open. De ingelogde
     * beheerder telt niet mee; als hij zelf deelnemer is stuurt
     * `EnsureAvailabilityIsSubmitted` hem direct naar het formulier. Op een
     * niet-actieve competitie dwingt niets af, dus daar volgt een toast die
     * zegt dat de vraag pas bij activeren speelt.
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

        if (! $competition->status->allowsAvailabilityChanges()) {
            $message = __('Match day added. Participants submit their availability once the competition is active.');
        } else {
            $pendingCount = $this->pendingAvailabilityParticipantsQuery($competition)
                ->whereKeyNot($request->user()->id)
                ->count();

            // Losse sleutelparen in plaats van trans_choice: dat is de conventie in
            // dit project (zie CompetitionAvailabilityReminderController).
            $message = match (true) {
                $pendingCount === 0 => __('Match day added.'),
                $pendingCount === 1 => __('Match day added. :count participant still has to submit their availability.', ['count' => $pendingCount]),
                default => __('Match day added. :count participants still have to submit their availability.', ['count' => $pendingCount]),
            };
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

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
        ]);
    }

    public function update(UpdateMatchDayRequest $request, Competition $competition, MatchDay $matchDay): RedirectResponse
    {
        $matchDay->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Match day updated.')]);

        return back();
    }

    public function destroy(Competition $competition, MatchDay $matchDay): RedirectResponse
    {
        $matchDay->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Match day removed.')]);

        return redirect()->route('competitions.edit', $competition);
    }
}
