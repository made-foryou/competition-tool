<?php

namespace App\Http\Middleware;

use App\Models\Competition;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laat alleen aan de competitie gekoppelde gebruikers (en admins) door naar
 * het deelnemersgedeelte.
 */
class EnsureUserParticipatesInCompetition
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $competition = $request->route('competition');

        abort_unless($competition instanceof Competition, 404);

        $user = $request->user();

        abort_unless($user instanceof User, 403);

        if ($user->isAdmin() || $competition->participants()->whereKey($user->getKey())->exists()) {
            return $next($request);
        }

        abort(403);
    }
}
