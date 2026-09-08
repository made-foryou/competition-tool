<?php

namespace App\Http\Controllers;

use App\Actions\Auth\SendInvitation;
use App\Enums\UserRole;
use App\Http\Requests\Competitions\StoreCompetitionParticipantRequest;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CompetitionParticipantController extends Controller
{
    public function store(StoreCompetitionParticipantRequest $request, Competition $competition, SendInvitation $sendInvitation): RedirectResponse
    {
        $email = $request->string('email')->toString();

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            $competition->participants()->syncWithoutDetaching([$existing->id]);

            return back();
        }

        if ($request->string('mode')->toString() === 'invite') {
            $sendInvitation->handle($email, UserRole::Participant, $competition, $request->user());

            return back();
        }

        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $email,
            'password' => $request->string('password')->toString(),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $competition->participants()->attach($user);

        return back();
    }

    public function destroy(Competition $competition, User $user): RedirectResponse
    {
        $competition->participants()->detach($user);

        return back();
    }
}
