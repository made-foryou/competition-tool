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
            $table->foreignId('first_player_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('second_player_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('match_day_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('match_day_field_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default(MatchStatus::Pending->value);
            $table->unsignedTinyInteger('first_player_score')->nullable();
            $table->unsignedTinyInteger('second_player_score')->nullable();
            $table->timestamps();

            // Spelersparen worden canoniek opgeslagen (laagste user-id als
            // first_player_id), zodat deze unique-index dubbele paren in
            // beide richtingen uitsluit.
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
