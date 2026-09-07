<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dwingt af dat elke ingelogde gebruiker een tweede factor heeft (TOTP of
 * passkey) voordat de applicatie toegankelijk is. Gebruikers zonder tweede
 * factor worden naar de verplichte setup-pagina gestuurd.
 */
class EnsureTwoFactorIsConfigured
{
    /**
     * Routes die bereikbaar moeten blijven om de setup af te ronden of om
     * uit te loggen.
     *
     * @var list<string>
     */
    protected array $allowedRoutes = [
        'two-factor.setup',
        'logout',
        'home',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
        'passkey.registration-options',
        'passkey.store',
        'well-known.passkeys',
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

        if (! Features::enabled(Features::twoFactorAuthentication()) && ! Features::enabled(Features::passkeys())) {
            return $next($request);
        }

        if ($user->hasEnabledTwoFactorAuthentication() || $user->hasPasskeysEnabled()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), $this->allowedRoutes, true)) {
            return $next($request);
        }

        return redirect()->route('two-factor.setup');
    }
}
