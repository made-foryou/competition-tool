<?php

namespace App\Actions\Competitions;

use App\Concerns\SummarizesAvailability;
use App\Models\Competition;
use App\Notifications\AvailabilityReminderNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Verstuurt een beschikbaarheidsherinnering naar alle deelnemers van een
 * competitie die hun beschikbaarheid nog niet hebben ingediend, en legt vast
 * wanneer die ronde verstuurd is.
 *
 * De timestamp `availability_reminder_sent_at` wordt alleen gezet wanneer er
 * daadwerkelijk mail de deur uit gaat: is iedereen al klaar met invullen, dan
 * zou het zetten van de timestamp de beheerder een etmaal blokkeren voor een
 * ronde die nooit heeft plaatsgevonden. In dat geval gebeurt er niets en komt
 * er `0` terug, waarna de controller de beheerder daarover informeert.
 *
 * Verzenden gebeurt met `Notification::send()` op de hele collectie in plaats
 * van met een `notify()` per deelnemer: de notification is queued, dus dit
 * levert één doorloop op waarin Laravel zelf per notifiable een job op de
 * queue zet, zonder losse lus in deze action.
 */
class SendAvailabilityReminders
{
    use SummarizesAvailability;

    /**
     * Retourneert het aantal deelnemers dat een herinnering heeft gekregen.
     */
    public function handle(Competition $competition): int
    {
        $participants = $this->pendingAvailabilityParticipants($competition);

        if ($participants->isEmpty()) {
            return 0;
        }

        Notification::send($participants, new AvailabilityReminderNotification($competition));

        // Kolom is bewust niet mass-assignable, dus expliciet zetten met forceFill().
        $competition->forceFill(['availability_reminder_sent_at' => now()])->save();

        return $participants->count();
    }
}
