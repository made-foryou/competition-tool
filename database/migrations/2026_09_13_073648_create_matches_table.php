<?php

use App\Enums\MatchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();

            // restrictOnDelete: gespeelde wedstrijden zijn historisch
            // resultaat. Het latere gebruikersbeheer moet bij het verwijderen
            // van een speler een bewuste keuze maken over diens wedstrijden
            // in plaats van ze stilzwijgend mee te wissen.
            $table->foreignId('first_player_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('second_player_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('match_day_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('match_day_field_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default(MatchStatus::Pending->value);
            $table->unsignedTinyInteger('first_player_score')->nullable();
            $table->unsignedTinyInteger('second_player_score')->nullable();
            $table->timestamps();

            // Deze unique-index weert exacte duplicaten, maar kan
            // spiegelparen (A-B naast B-A) níét uitsluiten. De canoniciteit
            // (laagste user-id als first_player_id) wordt afgedwongen door de
            // saving-hook op CompetitionMatch en door SyncCompetitionMatches,
            // die zijn paren zelf canoniek opbouwt.
            $table->unique(['competition_id', 'first_player_id', 'second_player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
