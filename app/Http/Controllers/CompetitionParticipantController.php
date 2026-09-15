<?php

namespace App\Http\Controllers;

use App\Actions\Auth\AcceptPendingInvitations;
use App\Actions\Auth\SendInvitation;
use App\Actions\Competitions\SyncCompetitionMatches;
use App\Enums\UserRole;
use App\Http\Requests\Competitions\StoreCompetitionParticipantRequest;
use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CompetitionParticipantController extends Controller
{
    /**
     * De gekozen `mode` bepaalt wat er gebeurt, niet of het account al
     * bestaat: uitnodigen laat de deelnemer zelf beslissen, koppelen en
     * aanmaken zetten hem er meteen bij.
     */
    public function store(StoreCompetitionParticipantRequest $request, Competition $competition, SendInvitation $sendInvitation, SyncCompetitionMatches $syncCompetitionMatches, AcceptPendingInvitations $acceptPendingInvitations): RedirectResponse
    {
        $email = $request->string('email')->toString();
        $mode = $request->string('mode')->toString();

        if ($mode === 'invite') {
            $sendInvitation->handle($email, UserRole::Participant, $competition, $request->user());

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

            return back();
        }

        if ($mode === 'link') {
            $existing = User::query()->where('email', $email)->firstOrFail();

            // Pivot-mutatie en wedstrijdensync atomair, consistent met de
            // invitation- en registratieflow: geen deelnemer zonder
            // bijbehorende wedstrijdenlijst.
            DB::transaction(function () use ($competition, $existing, $syncCompetitionMatches, $acceptPendingInvitations): void {
                $competition->participants()->syncWithoutDetaching([$existing->id]);

                $syncCompetitionMatches->handle($competition);

                // Wie handmatig gekoppeld wordt, hoeft niet meer te antwoorden
                // op een openstaande uitnodiging.
                $acceptPendingInvitations->handle($existing, $competition);
            });

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Participant linked. The match list has been updated.')]);

            return back();
        }

        DB::transaction(function () use ($request, $email, $competition, $syncCompetitionMatches, $acceptPendingInvitations): void {
            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => $email,
                'password' => $request->string('password')->toString(),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            $competition->participants()->attach($user);

            $syncCompetitionMatches->handle($competition);

            $acceptPendingInvitations->handle($user, $competition);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Participant added. The match list has been updated.')]);

        return back();
    }

    /**
     * Vertelt het toevoegformulier of een e-mailadres al een account heeft,
     * zodat de beheerder de juiste keuzes te zien krijgt. Adviserend: de
     * validatieregel in StoreCompetitionParticipantRequest blijft beslissen.
     */
    public function lookup(Request $request): JsonResponse
    {
        $email = $request->string('email')->toString();

        $user = $email === '' ? null : User::query()->where('email', $email)->first();

        return response()->json([
            'exists' => $user !== null,
            'name' => $user?->display_name,
        ]);
    }

    /**
     * De beschikbaarheid van de deelnemer gaat mee met de ontkoppeling.
     * Anders staan er bij opnieuw toevoegen vinkjes op speeldagen zonder dat
     * de deelnemer ooit iets heeft ingediend.
     */
    public function destroy(Competition $competition, User $participant, SyncCompetitionMatches $syncCompetitionMatches): RedirectResponse
    {
        DB::transaction(function () use ($competition, $participant, $syncCompetitionMatches): void {
            $competition->participants()->detach($participant);

            MatchDayAvailability::query()
                ->where('user_id', $participant->id)
                ->whereIn('match_day_id', MatchDay::query()->where('competition_id', $competition->id)->select('id'))
                ->delete();

            $syncCompetitionMatches->handle($competition);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Participant removed. The match list has been updated.')]);

        return back();
    }
}
