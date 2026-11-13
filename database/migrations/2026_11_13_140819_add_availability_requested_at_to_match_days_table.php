<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `availability_requested_at` is het moment waarop deelnemers om hun
     * beschikbaarheid voor deze speeldag gevraagd worden: gezet bij het
     * aanmaken en bij elke datumwijziging (#81). Bestaande speeldagen krijgen
     * `created_at`, zodat de deploy gedragsneutraal is.
     *
     * Een rij zonder `created_at` én `updated_at` zou na de backfill nog
     * `NULL` zijn en de `->change()` naar non-null laten falen. Op MySQL is
     * DDL niet transactioneel, dus dan blijft de migratie half uitgevoerd
     * achter. De terugval `1970-01-01 00:00:01` is gedragsneutraal: de oude
     * regel negeerde zo'n rij ook (`MAX` sloeg `NULL` over en `isNewSince()`
     * gaf `false`), en 1970 ligt vóór elke inzending.
     *
     * Bewust zonder `after()`: op MySQL zonder `explicit_defaults_for_timestamp`
     * krijgt de eerste `TIMESTAMP NOT NULL` vóór `created_at` anders automatisch
     * `ON UPDATE CURRENT_TIMESTAMP`.
     */
    public function up(): void
    {
        Schema::table('match_days', function (Blueprint $table) {
            $table->timestamp('availability_requested_at')->nullable();
        });

        DB::table('match_days')->update([
            'availability_requested_at' => DB::raw("COALESCE(created_at, updated_at, '1970-01-01 00:00:01')"),
        ]);

        Schema::table('match_days', function (Blueprint $table) {
            $table->timestamp('availability_requested_at')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_days', function (Blueprint $table) {
            $table->dropColumn('availability_requested_at');
        });
    }
};
