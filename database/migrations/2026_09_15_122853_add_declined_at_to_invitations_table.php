<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Een genodigde kan een uitnodiging voortaan expliciet afwijzen. Dat is
     * iets anders dan hem laten verlopen: de beheerder weet daarmee dat er
     * een antwoord is gekomen en hoeft niet te blijven rappelleren.
     */
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->timestamp('declined_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropColumn('declined_at');
        });
    }
};
