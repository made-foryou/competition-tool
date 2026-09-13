<?php

namespace App\Actions\Competitions;

use App\Concerns\SummarizesAvailability;
use App\Models\Competition;
use App\Notifications\AvailabilityReminderNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Verstuurt een beschikbaarheidsherinnering naar alle deelnemers van een
 * competitie die hun beschikbaarheid nog niet hebben ingediend, en legt vast
 * wanneer die ronde verstuurd is.
 *
 * Het venster van 24 uur wordt hier geclaimd met één voorwaardelijke UPDATE
 * vóór het versturen. Zou de action pas ná `Notification::send()` schrijven,
 * dan passeren twee gelijktijdige verzoeken (dubbele submit, twee tabbladen)
 * allebei de venstercheck van de controller en gaat er twee keer een volledige
 * ronde de deur uit.
 *
 * De timestamp blijft alleen staan wanneer er daadwerkelijk mail de deur uit
 * gaat: is iedereen al klaar met invullen, dan wordt het claim teruggedraaid.
 * Anders zou de beheerder een etmaal geblokkeerd zijn voor een ronde die nooit
 * heeft plaatsgevonden.
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
     * Verstuurt een ronde herinneringen en meldt de uitkomst.
     *
     * Drie mogelijke uitkomsten:
     *
     * - `null`: het venster van 24 uur was al geclaimd, door een eerdere ronde
     *   of door een gelijktijdig verzoek dat er als eerste bij was. Er is niets
     *   verstuurd; de controller meldt dit als "er is al een herinnering
     *   verstuurd".
     * - `0`: het venster was vrij, maar er stond niemand meer open. Er is niets
     *   verstuurd en het venster is weer vrijgegeven.
     * - een positief getal: het aantal deelnemers dat een herinnering heeft
     *   gekregen. Het venster blijft geclaimd.
     */
    public function handle(Competition $competition): ?int
    {
        $claimedAt = now();

        // Strikt kleiner dan, net als availabilityReminderAvailableAt(): op
        // exact sent_at + 24 uur is het venster nog dicht. Met <= zouden de
        // claim-query en de modelhelper op die ene seconde uiteenlopen.
        $claimed = Competition::query()
            ->whereKey($competition->getKey())
            ->where(fn (Builder $query) => $query
                ->whereNull('availability_reminder_sent_at')
                ->orWhere('availability_reminder_sent_at', '<', $claimedAt->copy()->subHours(Competition::AVAILABILITY_REMINDER_INTERVAL_HOURS)))
            ->update(['availability_reminder_sent_at' => $claimedAt]);

        if ($claimed !== 1) {
            return null;
        }

        // Kolom is bewust niet mass-assignable, dus expliciet zetten met
        // forceFill(). syncOriginalAttribute() houdt het model schoon: de
        // waarde staat immers al zo in de database.
        $competition->forceFill(['availability_reminder_sent_at' => $claimedAt])
            ->syncOriginalAttribute('availability_reminder_sent_at');

        $participants = $this->pendingAvailabilityParticipants($competition);

        if ($participants->isEmpty()) {
            Competition::query()
                ->whereKey($competition->getKey())
                ->update(['availability_reminder_sent_at' => null]);

            $competition->forceFill(['availability_reminder_sent_at' => null])
                ->syncOriginalAttribute('availability_reminder_sent_at');

            return 0;
        }

        Notification::send($participants, new AvailabilityReminderNotification($competition));

        return $participants->count();
    }
}
