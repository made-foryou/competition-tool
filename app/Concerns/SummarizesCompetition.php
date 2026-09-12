<?php

namespace App\Concerns;

use App\Models\Competition;

/**
 * Levert het minimale competitie-object dat de ParticipantLayout nodig heeft
 * voor de header: de naam om te tonen en de slug om de deelnemersroutes mee
 * op te bouwen.
 */
trait SummarizesCompetition
{
    /**
     * @return array{name: string, slug: string}
     */
    protected function competitionSummary(Competition $competition): array
    {
        return [
            'name' => $competition->name,
            'slug' => $competition->slug,
        ];
    }
}
