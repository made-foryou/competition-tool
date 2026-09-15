<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `starts_at`/`ends_at` zijn `time`-kolommen, net als op `match_days`:
     * het is lokale kloktijd op de speeldag, geen absoluut moment. De
     * eindtijd wordt apart opgeslagen zodat een historische planning niet
     * verschuift als de wedstrijdduur later wijzigt. De unique-index op
     * (match_day_field_id, starts_at) is een vangnet op databaseniveau tegen
     * dubbele tafelbezetting; NULL's conflicteren niet met elkaar, dus
     * ongeplande wedstrijden botsen niet.
     */
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->time('starts_at')->nullable()->after('match_day_field_id');
            $table->time('ends_at')->nullable()->after('starts_at');
            $table->timestamp('pinned_at')->nullable()->after('ends_at');
            $table->string('scheduling_failure')->nullable()->after('pinned_at');

            $table->index(['match_day_id', 'match_day_field_id', 'starts_at'], 'matches_schedule_index');
            $table->unique(['match_day_field_id', 'starts_at'], 'matches_field_slot_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropUnique('matches_field_slot_unique');
            $table->dropIndex('matches_schedule_index');
            $table->dropColumn(['starts_at', 'ends_at', 'pinned_at', 'scheduling_failure']);
        });
    }
};
