<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Maakt (of vervangt) een uitnodiging en verstuurt de uitnodigingsmail.
 */
class SendInvitation
{
    /**
     * Hoe lang een uitnodiging geldig blijft, in dagen. Ruim genomen: een
     * seizoensuitnodiging valt al snel samen met een vakantie, en de link
     * levert geen toegang op zonder dat de ontvanger kan inloggen of zelf een
     * wachtwoord zet.
     */
    protected int $expiresAfterDays = 30;

    /**
     * Retourneert de plain token voor de accept-link.
     */
    public function handle(string $email, UserRole $role, ?Competition $competition = null, ?User $inviter = null): string
    {
        $plainToken = Str::random(64);

        // Vervangen en aanmaken in één transactie: zonder dat leveren twee
        // bijna gelijktijdige verzoeken (dubbelklik op "opnieuw versturen")
        // twee rijen voor hetzelfde adres op.
        $invitation = DB::transaction(function () use ($email, $role, $competition, $inviter, $plainToken): Invitation {
            Invitation::query()
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->where('competition_id', $competition?->id)
                ->delete();

            $invitation = Invitation::query()->make([
                'email' => $email,
                'role' => $role,
                'expires_at' => now()->addDays($this->expiresAfterDays),
            ]);

            // Token, uitnodiger en competitie zijn bewust niet mass-assignable,
            // dus die zetten we hier expliciet met forceFill().
            $invitation->forceFill([
                'token' => hash('sha256', $plainToken),
                'invited_by' => $inviter?->id,
                'competition_id' => $competition?->id,
            ])->save();

            return $invitation;
        });

        // Of de ontvanger al een account heeft bepaalt de tekst van de mail.
        // Hier vastleggen en niet in de notificatie zelf: die is Queueable,
        // dus een lookup op afleveringsmoment kan tussen enqueue en verzending
        // omslaan.
        $hasAccount = User::query()->where('email', $email)->exists();

        Notification::route('mail', $email)
            ->notify(new InvitationNotification($invitation, $plainToken, $hasAccount));

        return $plainToken;
    }
}
