<?php

namespace App\Actions\Competitions;

use App\Models\Competition;
use App\Support\CompetitionSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Slaat de planningsinstellingen van een competitie op, inclusief het
 * wedstrijdformat en de puntentelling.
 *
 * Zodra er een uitslag is, liggen beide vast: alle uitslagen in een
 * competitie hebben gegarandeerd hetzelfde format en worden met dezelfde
 * telling tot een stand opgeteld; een wijziging halverwege zou de stand laten
 * verschuiven. `UpdateCompetitionSettingsRequest` controleert dat al voor de
 * directe veldfout, maar tegen een competitie die vóór de rijlock gelezen is.
 * Een gelijktijdige `RecordMatchResult` kan daarna nog de eerste uitslag
 * vastleggen; daarom hier opnieuw onder dezelfde lock.
 */
class UpdateCompetitionSettings
{
    /**
     * @throws ValidationException wanneer het format of de puntentelling wijzigt terwijl er intussen een uitslag is
     */
    public function handle(Competition $competition, CompetitionSettings $settings): void
    {
        DB::transaction(function () use ($competition, $settings): void {
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();

            $formatChanged = $settings->bestOf !== $locked->settings->bestOf;
            $pointsSystemChanged = $settings->pointsSystem !== $locked->settings->pointsSystem;

            if (($formatChanged || $pointsSystemChanged) && $locked->hasResults()) {
                $errors = [];

                if ($formatChanged) {
                    $errors['best_of'] = __('The match format cannot be changed once a result has been entered.');
                }

                if ($pointsSystemChanged) {
                    $errors['points_system'] = __('The points system cannot be changed once a result has been entered.');
                }

                throw ValidationException::withMessages($errors);
            }

            $locked->update(['settings' => $settings]);
        });
    }
}
