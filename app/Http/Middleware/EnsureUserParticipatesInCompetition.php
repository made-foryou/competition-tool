<?php

namespace App\Http\Middleware;

use App\Models\Competition;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Poortwachter van het deelnemersgedeelte: gasten gaan naar de competitie-
 * loginpagina en ingelogde niet-deelnemers naar de inschrijfpagina, zodat zij
 * zich (opnieuw) kunnen koppelen. Alleen aan de competitie gekoppelde
 * gebruikers en admins komen erdoorheen.
 *
 * Bewust geen 403 meer voor een ingelogde niet-deelnemer: de competitie is
 * publiek zichtbaar en de inschrijfpagina koppelt hem zelf weer. Een concept-
 * competitie is voor niet-admins al afgevangen met een 404 door
 * EnsureCompetitionIsVisible; een afgeronde competitie toont op de
 * inschrijfpagina de gesloten variant met een uitweg naar de eigen omgeving.
 */
class EnsureUserParticipatesInCompetition
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $competition = $request->route('competition');

        abort_unless($competition instanceof Competition, 404);

        $user = $request->user();

        if (! $user instanceof User) {
            return $this->redirectTo($request, 'competition.login', $competition);
        }

        if ($user->isAdmin() || $competition->participants()->whereKey($user->getKey())->exists()) {
            return $next($request);
        }

        return $this->redirectTo($request, 'competition.register.show', $competition);
    }

    /**
     * Een omleiding op een PUT/PATCH/DELETE hoort 303 te zijn, anders mag de
     * client zijn methode meenemen naar de GET-route waar hij op uitkomt.
     * Inertia corrigeert dat alleen voor zijn eigen requests, dus zetten we de
     * status hier zelf.
     */
    private function redirectTo(Request $request, string $route, Competition $competition): RedirectResponse
    {
        return redirect()->route($route, $competition, $request->isMethodSafe() ? 302 : 303);
    }
}
