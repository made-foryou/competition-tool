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
     *
     * De guard zit bewust in de delete-query zelf (whereNull('accepted_at'))
     * in plaats van in een aparte isAccepted()-check op het ingeladen model:
     * accepteert de genodigde precies tussen het laden en het verwijderen,
     * dan verwijdert een onvoorwaardelijke delete alsnog de inmiddels
     * geaccepteerde uitnodiging en ziet de beheerder "ingetrokken" terwijl
     * het account gewoon bestaat. Nul verwijderde rijen betekent dus: al
     * geaccepteerd, en dat geeft dezelfde 404.
     */
    public function destroy(Competition $competition, Invitation $invitation): RedirectResponse
    {
        $deleted = Invitation::query()
            ->whereKey($invitation->getKey())
            ->whereNull('accepted_at')
            ->delete();

        abort_if($deleted === 0, 404);

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
     * Participant zakt. De invited_by wordt bewust de beheerder die opnieuw
     * verstuurt: hij is de afzender van deze mail en het aanspreekpunt voor de
     * genodigde, ook al stond er eerder iemand anders als uitnodiger.
     */
    public function resend(Request $request, Competition $competition, Invitation $invitation, SendInvitation $sendInvitation): RedirectResponse
    {
        abort_if($invitation->isAccepted(), 404);

        $sendInvitation->handle($invitation->email, $invitation->role, $competition, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent again. The previous link no longer works.')]);

        return back();
    }
}
