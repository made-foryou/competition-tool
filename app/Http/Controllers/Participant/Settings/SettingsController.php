<?php

namespace App\Http\Controllers\Participant\Settings;

use App\Concerns\SummarizesCompetition;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het instellingenoverzicht van een deelnemer: de ingang naar profiel,
 * wachtwoord, beveiliging en weergave binnen de competitiecontext.
 */
class SettingsController extends Controller
{
    use SummarizesCompetition;

    public function __invoke(Competition $competition): Response
    {
        return Inertia::render('participant/settings/index', [
            'competition' => $this->competitionSummary($competition),
        ]);
    }
}
