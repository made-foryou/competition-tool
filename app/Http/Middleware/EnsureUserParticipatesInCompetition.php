<?php

namespace App\Http\Middleware;

use App\Models\Competition;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Poortwachter van het deelnemersgedeelte: stuurt gasten door naar de
 * competitie-loginpagina en laat daarna alleen aan de competitie gekoppelde
 * gebruikers (en admins) door.
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
            return redirect()->route('competition.login', $competition);
        }

        if ($user->isAdmin() || $competition->participants()->whereKey($user->getKey())->exists()) {
            return $next($request);
        }

        abort(403);
    }
}
