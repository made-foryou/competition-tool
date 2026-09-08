<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Maakt (of vervangt) een uitnodiging en verstuurt de uitnodigingsmail.
 */
class SendInvitation
{
    /**
     * Hoe lang een uitnodiging geldig blijft, in dagen.
     */
    protected int $expiresAfterDays = 7;

    /**
     * Retourneert de plain token voor de accept-link.
     */
    public function handle(string $email, UserRole $role, ?Competition $competition = null, ?User $inviter = null): string
    {
        Invitation::query()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('competition_id', $competition?->id)
            ->delete();

        $plainToken = Str::random(64);

        $invitation = Invitation::create([
            'email' => $email,
            'token' => hash('sha256', $plainToken),
            'invited_by' => $inviter?->id,
            'competition_id' => $competition?->id,
            'role' => $role,
            'expires_at' => now()->addDays($this->expiresAfterDays),
        ]);

        Notification::route('mail', $email)
            ->notify(new InvitationNotification($invitation, $plainToken));

        return $plainToken;
    }
}
