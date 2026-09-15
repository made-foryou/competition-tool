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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    use DeterminesLoginDestination;

    /**
     * Toon de uitnodigingspagina, of stuur de genodigde meteen door naar de
     * plek waar hij zich kan aanmelden.
     */
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invitation = Invitation::findByToken($token);

        abort_if($invitation === null, 404);

        $state = $this->stateOf($invitation);
        $user = $request->user();
        $competition = $invitation->competition;

        if ($user !== null && ! $this->isInvitee($user, $invitation)) {
            // Niet stilzwijgend uitloggen: wie per ongeluk op de link van een
            // huisgenoot klikt, raakt anders zijn eigen sessie kwijt.
            return Inertia::render('auth/accept-invitation', [
                'invitationState' => 'wrong-account',
                'email' => $invitation->email,
            ]);
        }

        if ($state === 'sign-in') {
            if ($user !== null) {
                // Wel hierheen sturen, niet koppelen: zelf beslissen of je
                // meedoet is de hele bedoeling van de uitnodiging.
                return $competition !== null
                    ? redirect()->route('competition.register.show', $competition)
                    : redirect()->to($this->defaultUrlFor($user));
            }

            if ($competition !== null) {
                // Bewust het dashboard als bestemming en niet de
                // inschrijfpagina: zo beslist EnsureUserParticipatesInCompetition
                // waar hij hoort, en staat iemand die intussen al gekoppeld is
                // meteen goed.
                $request->session()->put('url.intended', route('competition.dashboard', $competition));
            }

            return Inertia::render('auth/accept-invitation', [
                'invitationState' => $state,
                'email' => $invitation->email,
                'token' => $token,
                'competitionSlug' => $competition?->slug,
                'competitionName' => $competition?->name,
            ]);
        }

        // Verlopen, afgewezen, afgerond en nog-niet-geopend hebben niets van de
        // uitnodiging nodig om hun uitleg te tonen, dus geven we ook niets mee.
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
     * Wijs de uitnodiging af. Definitief: wil de genodigde later toch
     * meedoen, dan stuurt de beheerder een nieuwe uitnodiging.
     */
    public function decline(string $token): RedirectResponse
    {
        $invitation = Invitation::findByToken($token);

        abort_if($invitation === null, 404);

        if ($invitation->isUsable()) {
            $invitation->forceFill(['declined_at' => now()])->save();
        }

        return redirect()->route('invitation.show', $token);
    }

    /**
     * Accepteer de uitnodiging: maak het account aan en log de gebruiker in.
     */
    public function store(AcceptInvitationRequest $request, string $token, SyncCompetitionMatches $syncCompetitionMatches): RedirectResponse
    {
        $invitation = Invitation::findByToken($token);

        abort_if($invitation === null, 404);

        // Deze route maakt een account aan en logt in, dus hij is alleen voor
        // uitgelogde bezoekers. Sinds de route buiten de guest-groep valt moet
        // die check hier staan.
        if ($request->user() !== null) {
            return redirect()->route('invitation.show', $token);
        }

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
     * 'declined' is bewust afgewezen en 'expired' is verlopen of al gebruikt.
     *
     * 'closed' en 'upcoming' zijn op zichzelf nog geldige uitnodigingen voor
     * een competitie die geen aanmeldingen aanneemt -- wanneer dat zo is,
     * weet de status zelf (CompetitionStatus::allowsSignUp()). De twee staten
     * verschillen alleen in de reden, net als de registrationState van de
     * inschrijfpagina: afgerond tegenover nog niet geopend. Die reden gaat
     * voor op 'sign-in': waarom het niet kan is nuttiger dan "log in".
     *
     * 'sign-in' is een uitnodiging voor een e-mailadres dat al een account
     * heeft. Er valt dan niets te activeren; de genodigde logt in en meldt
     * zich via de gewone inschrijfpagina aan.
     *
     * @return 'open'|'declined'|'expired'|'closed'|'upcoming'|'sign-in'
     */
    protected function stateOf(Invitation $invitation): string
    {
        if ($invitation->isDeclined()) {
            return 'declined';
        }

        if (! $invitation->isUsable()) {
            return 'expired';
        }

        $competition = $invitation->competition;

        if ($competition !== null && ! $competition->status->allowsSignUp()) {
            return $competition->status === CompetitionStatus::Finished ? 'closed' : 'upcoming';
        }

        if (User::query()->where('email', $invitation->email)->exists()) {
            return 'sign-in';
        }

        return 'open';
    }

    /**
     * Of de ingelogde gebruiker degene is voor wie de uitnodiging bedoeld is.
     * Case-insensitief: Fortify normaliseert login-invoer naar kleine letters,
     * maar een adres dat via de beheerder is ingevoerd niet.
     */
    protected function isInvitee(User $user, Invitation $invitation): bool
    {
        return mb_strtolower($user->email) === mb_strtolower($invitation->email);
    }
}
