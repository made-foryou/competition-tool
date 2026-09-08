<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\DeterminesLoginDestination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    use DeterminesLoginDestination;

    /**
     * Toon de accept-pagina voor een uitnodiging.
     */
    public function show(string $token): Response
    {
        $invitation = Invitation::findByToken($token);

        abort_if($invitation === null, 404);

        if (! $this->isAcceptable($invitation)) {
            return Inertia::render('auth/accept-invitation', [
                'expired' => true,
            ]);
        }

        return Inertia::render('auth/accept-invitation', [
            'expired' => false,
            'email' => $invitation->email,
            'token' => $token,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Accepteer de uitnodiging: maak het account aan en log de gebruiker in.
     */
    public function store(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = Invitation::findByToken($token);

        abort_if($invitation === null, 404);

        if (! $this->isAcceptable($invitation)) {
            return redirect()->route('invitation.show', $token);
        }

        $user = DB::transaction(function () use ($invitation, $request): User {
            $invitation->forceFill(['accepted_at' => now()])->save();

            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => $invitation->email,
                'password' => $request->string('password')->toString(),
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
                'role' => $invitation->role,
            ])->save();

            if ($invitation->competition_id !== null) {
                $user->competitions()->attach($invitation->competition_id);
            }

            return $user;
        });

        Auth::login($user);

        $request->session()->regenerate();
        $request->session()->put('auth.password_confirmed_at', time());

        if ($user->isAdmin()) {
            return redirect()->route('two-factor.setup');
        }

        return redirect()->to($this->defaultUrlFor($user));
    }

    /**
     * Een uitnodiging is bruikbaar zolang die niet verlopen of gebruikt is en
     * er nog geen account met het e-mailadres bestaat.
     */
    protected function isAcceptable(Invitation $invitation): bool
    {
        return $invitation->isUsable()
            && ! User::query()->where('email', $invitation->email)->exists();
    }
}
