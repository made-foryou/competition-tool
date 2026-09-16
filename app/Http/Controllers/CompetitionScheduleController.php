<?php

namespace App\Http\Controllers;

use App\Actions\Competitions\ScheduleCompetitionMatches;
use App\Enums\SchedulingBlocker;
use App\Enums\SchedulingMode;
use App\Models\Competition;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CompetitionScheduleController extends Controller
{
    /**
     * Vult de nog ongeplande wedstrijden van een competitie aan rond het
     * bestaande schema.
     *
     * De statuscheck staat hier en niet in de Action: `ScheduleCompetitionMatches`
     * controleert `allowsScheduling()` bewust níet, omdat de planner ook door
     * toekomstige, niet-HTTP-aanroepers gebruikt moet kunnen worden. De
     * statusguard is een regel over schrijfroutes en hoort dus in de
     * HTTP-laag, net als bij de beschikbaarheidsherinneringen.
     *
     * Alle overige precondities komen uit de Action zelf, die ze via
     * `DeterminesSchedulingBlocker` bepaalt. Diezelfde trait voedt de
     * `blocked_reason` in `SummarizesSchedule::scheduleProps()`, in dezelfde
     * volgorde: eerst de status, daarna de precondities. De hint op de
     * uitgeschakelde knop en de toast na het indrukken kunnen daardoor niet
     * uiteenlopen.
     *
     * `nothing_to_schedule` is geen fout maar een mededeling — alles staat al
     * gepland — en krijgt daarom een neutrale toast in plaats van een
     * foutmelding.
     *
     * Er wordt bewust teruggestuurd naar de vorige pagina zonder tab-parameter:
     * de client bewaart zelf welke tab open stond, net als bij de andere
     * acties op de competitiepagina.
     */
    public function store(Competition $competition, ScheduleCompetitionMatches $scheduleCompetitionMatches): RedirectResponse
    {
        if (! $competition->status->allowsScheduling()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Matches can only be scheduled for an active competition.')]);

            return back();
        }

        $result = $scheduleCompetitionMatches->handle($competition, SchedulingMode::Fill);

        // `isBlocked()` zegt hetzelfde, maar de lokale variabele maakt ook
        // voor de statische analyse zichtbaar dat de reden hier gevuld is.
        $blocker = $result->blocker;

        if ($blocker instanceof SchedulingBlocker) {
            Inertia::flash('toast', [
                'type' => $result->isInformational() ? 'info' : 'error',
                'message' => $this->blockerMessage($blocker),
            ]);

            return back();
        }

        if ($result->isComplete()) {
            // Losse sleutelparen in plaats van trans_choice: dat is de
            // conventie in dit project (zie ook resources/js/lib/plural.ts aan
            // de frontendkant).
            $message = $result->scheduledCount === 1
                ? __(':count match scheduled.', ['count' => $result->scheduledCount])
                : __(':count matches scheduled.', ['count' => $result->scheduledCount]);

            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'warning',
            'message' => __(':scheduled matches scheduled, :unscheduled could not be scheduled. See the report.', [
                'scheduled' => $result->scheduledCount,
                'unscheduled' => $result->unscheduledCount,
            ]),
        ]);

        return back();
    }

    /**
     * De tekst per preconditie. Eén plek, zodat een nieuwe blocker meteen
     * zichtbaar wordt als ontbrekende `match`-tak.
     */
    private function blockerMessage(SchedulingBlocker $blocker): string
    {
        return match ($blocker) {
            SchedulingBlocker::NoMatchDays => __('Add match days before scheduling.'),
            SchedulingBlocker::NoFields => __('Add fields to at least one match day before scheduling.'),
            SchedulingBlocker::NoAvailability => __('Nobody has filled in their availability yet.'),
            SchedulingBlocker::NoMatches => __('There are no matches to schedule.'),
            SchedulingBlocker::NothingToSchedule => __('All matches are already scheduled.'),
        };
    }
}
