<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Competitions\UpdateCompetitionParticipantRoleRequest;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CompetitionParticipantRoleController extends Controller
{
    /**
     * Wijzigt de rol van een deelnemer.
     *
     * De rol is een eigenschap van de gebruiker, niet van de koppeling met
     * deze competitie: een admin is overal admin. Deze route hangt toch onder
     * de competitie-scope omdat de beheerder de deelnemer hier ook ziet en
     * omdat de scoped binding op {participant} zo gratis een 404 oplevert
     * voor iemand die geen deelnemer van deze competitie is.
     *
     * Zelfwijziging is verboden: wie zichzelf degradeert sluit zichzelf
     * meteen buiten de console, want EnsureUserIsAdmin geeft daarna een 403
     * op elke adminpagina en er is niemand meer om dat terug te draaien.
     *
     * De laatste-beheerder-guard voorkomt dat de admin-rol via de
     * deelnemerslijst helemaal leegloopt (net als bij het verwijderen van een
     * account in ProfileController::destroy()). Die staat bewust *voor* de
     * zelfwijzigingscheck: wie hier degradeert is zelf beheerder en mag nooit
     * zichzelf als doelwit hebben, dus buiten het zelf-geval zijn er altijd al
     * minstens twee beheerders en zou de guard onbereikbare code zijn. Zo
     * krijgt de laatste beheerder die zichzelf probeert te degraderen de
     * inhoudelijke melding in plaats van een kale 403.
     *
     * Die melding gaat bewust als toast-error via `back()` en niet als
     * ValidationException: de knop hangt achter een ConfirmDialog dat nooit
     * een `errors`-bag uitleest, dus een validatiefout zou hier alleen de
     * generieke "Something went wrong."-toast opleveren in plaats van de
     * echte reden.
     */
    public function __invoke(UpdateCompetitionParticipantRoleRequest $request, Competition $competition, User $participant): RedirectResponse
    {
        $role = UserRole::from($request->validated('role'));

        // Alleen relevant bij een daadwerkelijke degradatie van Admin naar
        // Participant: is de deelnemer al Participant, dan is isAdmin() al
        // false en slaat deze guard nooit onterecht toe op de idempotente
        // aanvraag "zet de huidige rol nogmaals".
        if ($role === UserRole::Participant && $participant->isAdmin() && User::query()->where('role', UserRole::Admin)->count() <= 1) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot remove the last administrator.')]);

            return back();
        }

        abort_if($participant->is($request->user()), 403);

        $wasAdmin = $participant->isAdmin();

        // forceFill omdat `role` bewust niet in #[Fillable] op User staat;
        // dat is het patroon dat de rest van de app ook gebruikt voor
        // guarded attributen (zie SendInvitation en InvitationController).
        $participant->forceFill(['role' => $role])->save();

        // De degradatiemelding is een overgang, geen toestand: wie nooit
        // beheerder was, is niet "geen beheerder meer". Via de UI is dat pad
        // onbereikbaar (de knop wisselt altijd van rol), maar een herhaald of
        // handmatig verzoek mag geen onwaarheid melden.
        $message = match (true) {
            $role === UserRole::Admin => __(':name is now an administrator.', ['name' => $participant->display_name]),
            $wasAdmin => __(':name is no longer an administrator.', ['name' => $participant->display_name]),
            default => __(':name is a participant.', ['name' => $participant->display_name]),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
