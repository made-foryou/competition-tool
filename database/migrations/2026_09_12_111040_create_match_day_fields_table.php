<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_day_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_day_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['match_day_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_day_fields');
    }
};
