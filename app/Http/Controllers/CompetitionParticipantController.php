<?php

namespace App\Http\Controllers;

use App\Actions\Auth\SendInvitation;
use App\Actions\Competitions\SyncCompetitionMatches;
use App\Enums\UserRole;
use App\Http\Requests\Competitions\StoreCompetitionParticipantRequest;
use App\Models\Competition;
use App\Models\MatchDayAvailability;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CompetitionParticipantController extends Controller
{
    public function store(StoreCompetitionParticipantRequest $request, Competition $competition, SendInvitation $sendInvitation, SyncCompetitionMatches $syncCompetitionMatches): RedirectResponse
    {
        $email = $request->string('email')->toString();

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            // Pivot-mutatie en wedstrijdensync atomair, consistent met de
            // invitation- en registratieflow: geen deelnemer zonder
            // bijbehorende wedstrijdenlijst.
            DB::transaction(function () use ($competition, $existing, $syncCompetitionMatches): void {
                $competition->participants()->syncWithoutDetaching([$existing->id]);

                $syncCompetitionMatches->handle($competition);
            });

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Participant linked. The match list has been updated.')]);

            return back();
        }

        if ($request->string('mode')->toString() === 'invite') {
            $sendInvitation->handle($email, UserRole::Participant, $competition, $request->user());

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

            return back();
        }

        DB::transaction(function () use ($request, $email, $competition, $syncCompetitionMatches): void {
            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => $email,
                'password' => $request->string('password')->toString(),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            $competition->participants()->attach($user);

            $syncCompetitionMatches->handle($competition);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Participant added. The match list has been updated.')]);

        return back();
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
                ->whereIn('match_day_id', $competition->matchDays()->select('id'))
                ->delete();

            $syncCompetitionMatches->handle($competition);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Participant removed. The match list has been updated.')]);

        return back();
    }
}
