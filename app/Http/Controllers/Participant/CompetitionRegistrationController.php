<?php

namespace App\Http\Controllers\Participant;

use App\Actions\Auth\AcceptPendingInvitations;
use App\Actions\Competitions\SyncCompetitionMatches;
use App\Concerns\DeterminesLoginDestination;
use App\Concerns\SummarizesMatchDay;
use App\Concerns\SyncsAvailability;
use App\Enums\CompetitionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Participant\StoreCompetitionRegistrationRequest;
use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Deelnemers melden zich hier zelf aan voor een competitie: account aanmaken,
 * koppelen en meteen de beschikbaarheid per speeldag vastleggen.
 */
class CompetitionRegistrationController extends Controller
{
    use DeterminesLoginDestination;
    use SummarizesMatchDay;
    use SyncsAvailability;

    /**
     * Alleen actieve competities tonen het inschrijfformulier. Een concept-
     * competitie ("upcoming", alleen zichtbaar voor admins) en een afgeronde
     * competitie ("closed") krijgen dezelfde pagina zonder speeldagen of
     * wachtwoordregels, met een per staat passende uitleg. Een ingelogde
     * gebruiker krijgt altijd een returnUrl mee, ongeacht de registratiestaat,
     * zodat de pagina nooit doodloopt.
     */
    public function show(Request $request, Competition $competition): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $competition->hasParticipant($user)) {
            return redirect()->route('competition.dashboard', $competition);
        }

        $registrationState = match ($competition->status) {
            CompetitionStatus::Active => 'open',
            CompetitionStatus::Draft => 'upcoming',
            CompetitionStatus::Finished => 'closed',
        };

        $isOpen = $registrationState === 'open';

        return Inertia::render('auth/competition-register', [
            'competitionName' => $competition->name,
            'competitionSlug' => $competition->slug,
            'authenticated' => $user !== null,
            'account' => $user !== null
                ? ['name' => $user->display_name, 'email' => $user->email]
                : null,
            'registrationState' => $registrationState,
            // De afwijzing van een verstuurd formulier: de staat hieronder
            // legt uit waarom aanmelden niet kan, de flash waarom de knop
            // niets deed.
            'status' => $request->session()->get('status'),
            // Zonder uitweg is de pagina doodlopend voor wie al ingelogd is:
            // hij belandt hier ook via een oude link of doordat hij zijn
            // koppeling met de competitie kwijt is, en de competitie-login
            // stuurt hem terug naar een dashboard waar hij geen deelnemer van
            // is.
            'returnUrl' => $user === null ? null : $this->defaultUrlFor($user),
            'matchDays' => $isOpen
                ? $competition->matchDays()
                    ->get()
                    ->map(fn (MatchDay $matchDay): array => $this->matchDayProps($matchDay))
                    ->all()
                : [],
            'passwordRules' => $isOpen
                ? Password::defaults()->toPasswordRulesString()
                : '',
        ]);
    }

    /**
     * Dezelfde race als bij een uitnodiging (InvitationController::store()):
     * de competitie kan afgerond raken terwijl het formulier openstaat. Terug
     * naar de inschrijfpagina, die de reden zelf toont -- een 404 zou het
     * ingevulde formulier stil laten verdampen.
     */
    public function store(StoreCompetitionRegistrationRequest $request, Competition $competition, SyncCompetitionMatches $syncCompetitionMatches, AcceptPendingInvitations $acceptPendingInvitations): RedirectResponse
    {
        if (! $competition->status->allowsSignUp()) {
            return redirect()
                ->route('competition.register.show', $competition, 303)
                ->with('status', $this->signUpClosedReason($competition));
        }

        $existing = $request->user();

        $user = DB::transaction(function () use ($request, $competition, $existing, $syncCompetitionMatches, $acceptPendingInvitations): User {
            $user = $existing ?? $this->createParticipant($request);

            $competition->participants()->syncWithoutDetaching([$user->id]);

            $this->syncAvailability($user, $competition, $request->validated('match_days') ?? []);

            $syncCompetitionMatches->handle($competition);

            // Deze pagina is de trechter voor beide routes -- de
            // uitnodigingslink en de doorgestuurde inschrijflink -- dus hier
            // vervalt een openstaande uitnodiging, hoe de deelnemer ook
            // binnenkwam.
            $acceptPendingInvitations->handle($user, $competition);

            return $user;
        });

        if ($existing === null) {
            Auth::login($user);

            $request->session()->regenerate();
            $request->session()->put('auth.password_confirmed_at', time());
        }

        return redirect()->route('competition.dashboard', $competition);
    }

    /**
     * Bewust een andere formulering dan de uitleg op de pagina zelf: die
     * vertelt waarom aanmelden niet kan, deze melding wat er met het zojuist
     * verstuurde formulier is gebeurd. Dezelfde zin twee keer onder elkaar
     * leest als een fout in de pagina.
     */
    protected function signUpClosedReason(Competition $competition): string
    {
        return $competition->status === CompetitionStatus::Finished
            ? __('Your sign-up was not processed: this competition has finished in the meantime.')
            : __('Your sign-up was not processed: sign-up has not opened yet.');
    }

    /**
     * Maakt het deelnemersaccount aan. Rol en e-mailverificatie staan niet in
     * de fillable-lijst, vandaar forceFill -- zelfde patroon als bij het
     * accepteren van een uitnodiging.
     */
    protected function createParticipant(StoreCompetitionRegistrationRequest $request): User
    {
        $user = User::create($request->safe()->only(['name', 'nickname', 'email', 'password']));

        $user->forceFill([
            'email_verified_at' => now(),
            'role' => UserRole::Participant,
        ])->save();

        return $user;
    }
}
