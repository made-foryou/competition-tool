<?php

namespace App\Http\Controllers;

use App\Actions\Auth\SendInvitation;
use App\Models\Competition;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompetitionInvitationController extends Controller
{
    /**
     * Trekt een openstaande uitnodiging in.
     *
     * Bewust geen guard op isExpired(): een verlopen uitnodiging opruimen is
     * precies waar deze feature voor bedoeld is. Alleen een al geaccepteerde
     * uitnodiging is geen openstaande uitnodiging meer en geeft een 404.
     */
    public function destroy(Competition $competition, Invitation $invitation): RedirectResponse
    {
        abort_if($invitation->isAccepted(), 404);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation withdrawn.')]);

        return back();
    }

    /**
     * Verstuurt een openstaande uitnodiging opnieuw.
     *
     * Bewust geen guard op isExpired(): juist een verlopen uitnodiging wil je
     * opnieuw kunnen versturen. Alleen een al geaccepteerde uitnodiging geeft
     * een 404.
     *
     * SendInvitation verwijdert zelf de bestaande niet-geaccepteerde
     * uitnodiging voor dezelfde email + competitie en maakt een nieuwe aan met
     * verse token en expires_at. De rol van de bestaande uitnodiging gaat
     * expliciet mee, zodat een adminuitnodiging niet stilletjes naar
     * Participant zakt.
     */
    public function resend(Request $request, Competition $competition, Invitation $invitation, SendInvitation $sendInvitation): RedirectResponse
    {
        abort_if($invitation->isAccepted(), 404);

        $sendInvitation->handle($invitation->email, $invitation->role, $competition, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent again. The previous link no longer works.')]);

        return back();
    }
}
