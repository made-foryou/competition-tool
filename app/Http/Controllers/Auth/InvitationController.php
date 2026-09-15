<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Competitions\SyncCompetitionMatches;
use App\Concerns\DeterminesLoginDestination;
use App\Enums\CompetitionStatus;
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

        $state = $this->stateOf($invitation);

        if ($state !== 'open') {
            return Inertia::render('auth/accept-invitation', [
                'invitationState' => $state,
            ]);
        }

        return Inertia::render('auth/accept-invitation', [
            'invitationState' => $state,
            'email' => $invitation->email,
            'token' => $token,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Accepteer de uitnodiging: maak het account aan en log de gebruiker in.
     */
    public function store(AcceptInvitationRequest $request, string $token, SyncCompetitionMatches $syncCompetitionMatches): RedirectResponse
    {
        $invitation = Invitation::findByToken($token);

        abort_if($invitation === null, 404);

        // Ook op de POST, niet alleen op de GET: de competitie kan tussen het
        // tonen van het formulier en het versturen ervan zijn afgerond.
        if ($this->stateOf($invitation) !== 'open') {
            return redirect()->route('invitation.show', $token);
        }

        $user = DB::transaction(function () use ($invitation, $request, $syncCompetitionMatches): User {
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

            if ($invitation->competition !== null) {
                $user->competitions()->attach($invitation->competition->id);

                // Binnen de bestaande transactie (genest is prima in
                // Laravel), zodat account, koppeling en wedstrijdenlijst
                // samen slagen of samen worden teruggedraaid.
                $syncCompetitionMatches->handle($invitation->competition);
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
     * De staat van de uitnodiging, gedeeld door show() en store() zodat de
     * pagina en de verwerking nooit een ander oordeel vellen.
     *
     * 'expired' dekt naast een verlopen uitnodiging ook een al gebruikte
     * uitnodiging en een e-mailadres dat inmiddels een account heeft: in alle
     * drie de gevallen is de gevraagde actie dezelfde, namelijk een nieuwe
     * uitnodiging vragen. Die reden gaat voor, ook als de competitie
     * daarnaast is afgerond.
     *
     * 'closed' en 'upcoming' zijn op zichzelf nog geldige uitnodigingen voor
     * een competitie die geen aanmeldingen aanneemt -- wanneer dat zo is,
     * weet de status zelf (CompetitionStatus::allowsSignUp()). De twee staten
     * verschillen alleen in de reden, net als de registrationState van de
     * inschrijfpagina: afgerond tegenover nog niet geopend.
     *
     * @return 'open'|'expired'|'closed'|'upcoming'
     */
    protected function stateOf(Invitation $invitation): string
    {
        if (! $invitation->isUsable() || User::query()->where('email', $invitation->email)->exists()) {
            return 'expired';
        }

        $competition = $invitation->competition;

        if ($competition !== null && ! $competition->status->allowsSignUp()) {
            return $competition->status === CompetitionStatus::Finished ? 'closed' : 'upcoming';
        }

        return 'open';
    }
}
