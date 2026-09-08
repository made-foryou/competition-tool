<?php

namespace App\Http\Middleware;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verbergt concept-competities voor iedereen behalve admins. Actieve en
 * afgeronde competities zijn zichtbaar.
 */
class EnsureCompetitionIsVisible
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $competition = $request->route('competition');

        abort_unless($competition instanceof Competition, 404);

        $user = $request->user();
        $isAdmin = $user instanceof User && $user->isAdmin();

        abort_if($competition->status === CompetitionStatus::Draft && ! $isAdmin, 404);

        return $next($request);
    }
}
