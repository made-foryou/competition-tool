<?php

namespace App\Http\Middleware;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dwingt af dat iedere deelnemer zijn beschikbaarheid invult voor elke actieve
 * competitie waaraan hij deelneemt. Wie dat nog niet heeft gedaan wordt naar
 * het beschikbaarheidsformulier gestuurd. Dat geldt ook voor een beheerder die
 * zelf meedoet; een beheerder die nergens deelnemer is wordt niet geraakt,
 * omdat de query via `$user->competitions()` loopt.
 */
class EnsureAvailabilityIsSubmitted
{
    /**
     * Sessiesleutel met `['competition_id' => int, 'url' => string]`: de url
     * van het Inertia-paginabezoek dat deze middleware onderschepte, gekoppeld
     * aan de competitie waarvoor naar het formulier is geredirect. Zo keert
     * `AvailabilityController::update()` na de eerste inzending terug naar
     * waar de gebruiker heen wilde. Bewust niet `url.intended`: die is van de
     * loginflow van Fortify.
     */
    public const string INTENDED_URL_SESSION_KEY = 'availability.intended_url';

    /**
     * Routes die bereikbaar moeten blijven om het formulier in te vullen, om
     * je voor een andere competitie aan te melden, of om uit te loggen.
     *
     * De uitnodigingsroutes staan er bewust bij: zonder dat wordt een
     * genodigde die nog beschikbaarheid open heeft staan bij een andere
     * actieve competitie weggekaapt naar dat formulier en bereikt hij zijn
     * uitnodiging nooit.
     *
     * @var list<string>
     */
    protected array $allowedRoutes = [
        'competition.availability.edit',
        'competition.availability.update',
        'competition.register.show',
        'competition.register.store',
        'invitation.show',
        'invitation.store',
        'invitation.decline',
        'logout',
        'home',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'verification.notice',
        'verification.verify',
        'verification.send',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        /*
         * De 2FA-setup gaat voor. EnsureTwoFactorIsConfigured draait eerder en
         * laat alleen zijn eigen routes door; zonder deze guard ontstaat een
         * redirect-lus tussen `two-factor.setup` en het beschikbaarheidsformulier.
         */
        if ($user->requiresSecondFactorSetup()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), $this->allowedRoutes, true)) {
            return $next($request);
        }

        $competition = $this->competitionAwaitingAvailability($user);

        if ($competition === null) {
            return $next($request);
        }

        /*
         * Een 302 op PUT/PATCH/DELETE laat een client zonder X-Inertia-header
         * (fetch, useHttp) zijn methode en body meenemen; dan wordt het
         * `PUT availability` en dient hij stil een lege beschikbaarheid in.
         * Daarom 303 bij niet-veilige methodes, zoals in
         * EnsureUserParticipatesInCompetition::redirectTo().
         */
        $response = redirect()->route('competition.availability.edit', $competition, $request->isMethodSafe() ? 302 : 303);

        /*
         * Onthoud de onderschepte pagina voor na de eerste inzending. Alleen
         * een Inertia-paginabezoek via GET: een JSON-, download- of andere
         * niet-Inertia-url zou na de inzending als Inertia-response mislukken,
         * een niet-GET-verzoek kunnen we niet terugspelen en een prefetch is
         * geen bezoek en mag de bestemming niet overschrijven. De koppeling aan
         * de competitie voorkomt dat een oude url in een andere flow opduikt.
         */
        if ($request->isMethod('GET') && $request->inertia() && ! $request->prefetch()) {
            $request->session()->put(self::INTENDED_URL_SESSION_KEY, [
                'competition_id' => $competition->id,
                'url' => $request->fullUrl(),
            ]);
        }

        return $response;
    }

    /**
     * De eerstvolgende actieve competitie waarvoor de deelnemer (opnieuw)
     * moet indienen: nooit ingediend, of ingediend vóór het nieuwste
     * beschikbaarheidsverzoek (`availability_requested_at`: speeldag
     * aangemaakt of verzet, `Competition::constrainToAwaitingAvailability()`).
     * Competities zonder speeldagen tellen niet mee: daar valt niets in te
     * vullen.
     */
    protected function competitionAwaitingAvailability(User $user): ?Competition
    {
        return Competition::constrainToAwaitingAvailability($user->competitions())
            ->where('competitions.status', CompetitionStatus::Active)
            ->whereHas('matchDays')
            ->orderByDesc('starts_at')
            ->first();
    }
}
