<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_day_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['match_day_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_day_availabilities');
    }
};
