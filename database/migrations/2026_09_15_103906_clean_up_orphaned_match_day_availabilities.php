<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Ruimt beschikbaarheid op van gebruikers die niet (meer) aan de competitie
 * van die speeldag gekoppeld zijn. Tot nu toe liet het verwijderen van een
 * deelnemer die rijen staan, waardoor hij bij opnieuw toevoegen vinkjes had
 * zonder ooit iets te hebben ingediend.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('match_day_availabilities')
            ->whereNotExists(fn (Builder $query) => $query
                ->select(DB::raw(1))
                ->from('match_days')
                ->join('competition_user', 'competition_user.competition_id', '=', 'match_days.competition_id')
                ->whereColumn('match_days.id', 'match_day_availabilities.match_day_id')
                ->whereColumn('competition_user.user_id', 'match_day_availabilities.user_id'),
            )
            ->delete();
    }

    /**
     * Verwijderde rijen zijn niet terug te halen, dus deze migratie kent geen
     * betekenisvolle terugweg.
     */
    public function down(): void
    {
        //
    }
};
