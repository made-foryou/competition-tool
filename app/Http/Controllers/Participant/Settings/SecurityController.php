<?php

namespace App\Http\Controllers\Participant\Settings;

use App\Concerns\BuildsSecurityProps;
use App\Concerns\SummarizesCompetition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\Competition;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tweestapsverificatie en passkeys voor de deelnemer. Beide zijn optioneel;
 * de route staat achter RequirePassword zodat Fortify de gebruiker niet
 * halverwege een handeling om bevestiging vraagt.
 */
class SecurityController extends Controller
{
    use BuildsSecurityProps, SummarizesCompetition;

    public function __invoke(TwoFactorAuthenticationRequest $request, Competition $competition): Response
    {
        return Inertia::render('participant/settings/security', [
            ...$this->securityProps($request),
            'competition' => $this->competitionSummary($competition),
        ]);
    }
}
