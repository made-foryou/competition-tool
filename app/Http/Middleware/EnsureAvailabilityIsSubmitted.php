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
 * het beschikbaarheidsformulier gestuurd; voor beheerders geldt dit niet.
 */
class EnsureAvailabilityIsSubmitted
{
    /**
     * Routes die bereikbaar moeten blijven om het formulier in te vullen of om
     * uit te loggen.
     *
     * @var list<string>
     */
    protected array $allowedRoutes = [
        'competition.availability.edit',
        'competition.availability.update',
        'competition.register.show',
        'competition.register.store',
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

        if (! $user instanceof User || $user->isAdmin()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), $this->allowedRoutes, true)) {
            return $next($request);
        }

        $competition = $this->competitionAwaitingAvailability($user);

        if ($competition === null) {
            return $next($request);
        }

        return redirect()->route('competition.availability.edit', $competition);
    }

    /**
     * De eerstvolgende actieve competitie waarvoor de deelnemer nog niets heeft
     * ingediend. Competities zonder speeldagen tellen niet mee: daar valt niets
     * in te vullen.
     */
    protected function competitionAwaitingAvailability(User $user): ?Competition
    {
        return $user->competitions()
            ->where('competitions.status', CompetitionStatus::Active)
            ->wherePivotNull('availability_submitted_at')
            ->whereHas('matchDays')
            ->orderByDesc('starts_at')
            ->first();
    }
}
