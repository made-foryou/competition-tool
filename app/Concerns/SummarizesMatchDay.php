<?php

namespace App\Concerns;

use App\Models\MatchDay;

/**
 * Levert de speeldag-props die zowel het overzicht op de competitiepagina als
 * de bewerkpagina van één speeldag gebruiken.
 */
trait SummarizesMatchDay
{
    /**
     * @return array{id: int, date: string, starts_at: string, ends_at: string}
     */
    protected function matchDayProps(MatchDay $matchDay): array
    {
        return [
            'id' => $matchDay->id,
            'date' => $matchDay->date->toDateString(),
            'starts_at' => $matchDay->starts_at,
            'ends_at' => $matchDay->ends_at,
        ];
    }
}
