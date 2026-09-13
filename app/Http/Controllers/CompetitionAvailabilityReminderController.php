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
     * Daarna pas het anti-spamvenster van 24 uur: dat is een grens op het
     * herhaald mailen van dezelfde deelnemers en hoort niet af te hangen van
     * een pogingen tot verzenden die op de statuscheck al zou stranden.
     *
     * De verzendactie zet `availability_reminder_sent_at` uitsluitend wanneer
     * er echt mail verstuurd is. Zonder openstaande deelnemers komt er `0`
     * terug en blijft het venster open: de beheerder zou anders een etmaal
     * geblokkeerd zijn door een ronde die nooit heeft plaatsgevonden, terwijl
     * er tussentijds best een nieuwe deelnemer bij kan komen die de
     * herinnering wél nodig heeft.
     */
    public function __invoke(Competition $competition, SendAvailabilityReminders $sendAvailabilityReminders): RedirectResponse
    {
        if ($competition->status !== CompetitionStatus::Active) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Reminders can only be sent for an active competition.')]);

            return back();
        }

        // Eén keer uitlezen in plaats van canSendAvailabilityReminder() plus een
        // tweede aanroep in de melding: zo weet ook de statische analyse dat de
        // waarde binnen deze tak geen null is.
        $availableAt = $competition->availabilityReminderAvailableAt();

        if ($availableAt !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('A reminder was already sent. You can send a new one from :time.', ['time' => $availableAt->translatedFormat('j F Y H:i')])]);

            return back();
        }

        $count = $sendAvailabilityReminders->handle($competition);

        if ($count === 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Everyone has already filled in their availability.')]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder sent to :count participants.', ['count' => $count])]);

        return back();
    }
}
