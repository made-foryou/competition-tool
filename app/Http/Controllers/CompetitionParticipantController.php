<?php

namespace App\Http\Controllers;

use App\Actions\Auth\SendInvitation;
use App\Actions\Competitions\SyncCompetitionMatches;
use App\Enums\UserRole;
use App\Http\Requests\Competitions\StoreCompetitionParticipantRequest;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CompetitionParticipantController extends Controller
{
    public function store(StoreCompetitionParticipantRequest $request, Competition $competition, SendInvitation $sendInvitation, SyncCompetitionMatches $syncCompetitionMatches): RedirectResponse
    {
        $email = $request->string('email')->toString();

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            $competition->participants()->syncWithoutDetaching([$existing->id]);

            $syncCompetitionMatches->handle($competition);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Participant linked.')]);

            return back();
        }

        if ($request->string('mode')->toString() === 'invite') {
            $sendInvitation->handle($email, UserRole::Participant, $competition, $request->user());

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

            return back();
        }

        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $email,
            'password' => $request->string('password')->toString(),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $competition->participants()->attach($user);

        $syncCompetitionMatches->handle($competition);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Participant added.')]);

        return back();
    }

    public function destroy(Competition $competition, User $user, SyncCompetitionMatches $syncCompetitionMatches): RedirectResponse
    {
        $competition->participants()->detach($user);

        $syncCompetitionMatches->handle($competition);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Participant removed.')]);

        return back();
    }
}
