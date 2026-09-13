<?php

namespace App\Http\Controllers;

use App\Actions\Competitions\SendAvailabilityReminders;
use App\Enums\CompetitionStatus;
use App\Models\Competition;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CompetitionAvailabilityReminderController extends Controller
{
    /**
     * Verstuurt een herinnering naar de deelnemers die hun beschikbaarheid nog
     * niet hebben ingediend.
     *
     * De statuscheck staat vooraan omdat een herinnering buiten een actieve
     * competitie schadelijk of zinloos is. Een concept-competitie is voor
     * deelnemers niet zichtbaar (EnsureCompetitionIsVisible), dus de knop in de
     * mail zou de ontvanger op een 404 laten landen — een mail die alleen maar
     * vragen oproept. Bij een afgeronde competitie staat het speelschema al
     * vast en heeft niemand er nog iets aan om beschikbaarheid door te geven.
     *
     * Daarna de speeldagen: zonder speeldagen landt de ontvanger op een leeg
     * formulier dat hij leeg kan indienen, waarna hij als "ingevuld" geboekt
     * staat en nooit meer een herinnering krijgt zodra de speeldagen er wél
     * zijn.
     *
     * Pas daarna het anti-spamvenster van 24 uur: dat is een grens op het
     * herhaald mailen van dezelfde deelnemers en hoort niet af te hangen van
     * een pogingen tot verzenden die op een eerdere check al zou stranden.
     *
     * De volgorde van deze checks is ook de volgorde waarin
     * `CompetitionController::availabilityReminderProps()` de `blocked_reason`
     * voor het scherm bepaalt. Wijkt één van de twee af, dan noemt het scherm
     * een andere reden dan de server bij het indrukken van de knop.
     *
     * De verzendactie claimt het venster zelf en meldt met `null` dat een
     * gelijktijdig verzoek er als eerste bij was; dat leidt tot dezelfde
     * melding als de venstercheck hierboven. Zonder openstaande deelnemers
     * komt er `0` terug en geeft de actie het venster weer vrij: de beheerder
     * zou anders een etmaal geblokkeerd zijn door een ronde die nooit heeft
     * plaatsgevonden, terwijl er tussentijds best een nieuwe deelnemer bij kan
     * komen die de herinnering wél nodig heeft.
     */
    public function __invoke(Competition $competition, SendAvailabilityReminders $sendAvailabilityReminders): RedirectResponse
    {
        if ($competition->status !== CompetitionStatus::Active) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Reminders can only be sent for an active competition.')]);

            return back();
        }

        if ($competition->matchDays()->exists() === false) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Add match days before sending a reminder.')]);

            return back();
        }

        // Geen absoluut tijdstip in de melding: `config('app.timezone')` staat
        // op UTC, terwijl de frontend hetzelfde moment in de browsertijdzone
        // toont. De beheerder zou dan twee tijdstippen voor hetzelfde moment
        // in één scherm zien. Het exacte moment staat client-side al in de
        // uitleg boven de matrix.
        if ($competition->availabilityReminderWindowIsOpen() === false) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('A reminder was already sent in the past 24 hours.')]);

            return back();
        }

        $count = $sendAvailabilityReminders->handle($competition);

        if ($count === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('A reminder was already sent in the past 24 hours.')]);

            return back();
        }

        if ($count === 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Everyone has already filled in their availability.')]);

            return back();
        }

        // Losse sleutelparen in plaats van trans_choice: dat is de conventie in
        // dit project (zie ook resources/js/lib/plural.ts aan de frontendkant).
        $message = $count === 1
            ? __('Reminder sent to :count participant.', ['count' => $count])
            : __('Reminder sent to :count participants.', ['count' => $count]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
