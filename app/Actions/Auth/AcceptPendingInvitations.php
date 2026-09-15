<?php

namespace App\Actions\Auth;

use App\Models\Competition;
use App\Models\Invitation;
use App\Models\User;

/**
 * Vinkt de openstaande uitnodigingen af van iemand die deelnemer is geworden.
 *
 * Wordt aangeroepen vanuit elk pad dat een deelnemer koppelt, niet alleen
 * vanuit de uitnodigingslink: wie zich aanmeldt via de doorgestuurde
 * inschrijflink of door de beheerder wordt gekoppeld, heeft net zo goed
 * geantwoord. Zonder dit blijft de beheerder rappelleren bij mensen die al
 * meedoen.
 */
class AcceptPendingInvitations
{
    public function handle(User $user, Competition $competition): void
    {
        // Query-builder in plaats van een geladen model: accepted_at is
        // bewust niet mass-assignable, en dit dekt meteen het geval van
        // meerdere rijen voor hetzelfde adres.
        Invitation::query()
            ->where('competition_id', $competition->id)
            ->where('email', $user->email)
            ->whereNull('accepted_at')
            ->update(['accepted_at' => now()]);
    }
}
