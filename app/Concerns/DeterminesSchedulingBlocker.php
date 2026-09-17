<?php

namespace App\Concerns;

use App\Enums\MatchStatus;
use App\Enums\SchedulingBlocker;
use App\Enums\SchedulingMode;
use App\Models\Competition;
use App\Models\MatchDayAvailability;
use App\Models\MatchDayField;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bepaalt of er voor een competitie überhaupt iets te plannen valt.
 *
 * De volgorde van de controles ís de prioriteit: de eerste die aanslaat is de
 * reden. Diezelfde reden gaat straks ook naar de UI-props (de hint op de
 * uitgeschakelde knop) en naar de toast na het indrukken, zodat die twee nooit
 * uiteen kunnen lopen.
 */
trait DeterminesSchedulingBlocker
{
    /**
     * De preconditie die het plannen blokkeert, of null als de planner mag
     * draaien.
     */
    protected function schedulingBlocker(Competition $competition, SchedulingMode $mode): ?SchedulingBlocker
    {
        if ($competition->matchDays()->doesntExist()) {
            return SchedulingBlocker::NoMatchDays;
        }

        if (MatchDayField::query()
            ->whereIn('match_day_id', $competition->matchDays()->select('id'))
            ->doesntExist()) {
            return SchedulingBlocker::NoFields;
        }

        // Alleen de beschikbaarheid van de huidige deelnemers telt: een
        // oud-deelnemer die nog rijen heeft staan, maakt de competitie niet
        // planbaar.
        if (MatchDayAvailability::query()
            ->whereIn('match_day_id', $competition->matchDays()->select('id'))
            ->whereIn('user_id', $competition->participants()->select('users.id'))
            ->doesntExist()) {
            return SchedulingBlocker::NoAvailability;
        }

        if ($competition->matches()->where('status', MatchStatus::Pending->value)->doesntExist()) {
            return SchedulingBlocker::NoMatches;
        }

        // Opnieuw plannen laat alles los en heeft dus altijd werk; aanvullen
        // alleen wanneer er nog een openstaande wedstrijd zonder volledige
        // plek is.
        if ($mode === SchedulingMode::Fill
            && $competition->matches()
                ->where('status', MatchStatus::Pending->value)
                ->where(fn (Builder $query) => $query
                    ->whereNull('match_day_id')
                    ->orWhereNull('match_day_field_id')
                    ->orWhereNull('starts_at'))
                ->doesntExist()) {
            return SchedulingBlocker::NothingToSchedule;
        }

        return null;
    }
}
