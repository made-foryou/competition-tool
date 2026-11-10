<?php

namespace App\Actions\Competitions;

use App\Models\Competition;
use App\Support\CompetitionSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Slaat de planningsinstellingen van een competitie op, inclusief het
 * wedstrijdformat.
 *
 * Zodra er een uitslag is, ligt het format vast: alle uitslagen in een
 * competitie hebben gegarandeerd hetzelfde format, anders trekt een
 * klassement op games scheef. `UpdateCompetitionSettingsRequest` controleert
 * dat al voor de directe veldfout, maar tegen een competitie die vóór de
 * rijlock gelezen is. Een gelijktijdige `RecordMatchResult` kan daarna nog de
 * eerste uitslag vastleggen; daarom hier opnieuw onder dezelfde lock.
 */
class UpdateCompetitionSettings
{
    /**
     * @throws ValidationException wanneer het format wijzigt terwijl er intussen een uitslag is
     */
    public function handle(Competition $competition, CompetitionSettings $settings): void
    {
        DB::transaction(function () use ($competition, $settings): void {
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();

            if ($settings->bestOf !== $locked->settings->bestOf && $locked->hasResults()) {
                throw ValidationException::withMessages([
                    'best_of' => __('The match format cannot be changed once a result has been entered.'),
                ]);
            }

            $locked->update(['settings' => $settings]);
        });
    }
}
