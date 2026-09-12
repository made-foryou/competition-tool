<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();
            $table->index(['competition_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_days');
    }
};
