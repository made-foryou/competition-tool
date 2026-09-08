<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->foreignId('competition_id')
                ->nullable()
                ->after('invited_by')
                ->constrained()
                ->cascadeOnDelete();
            // Bestaande uitnodigingen zijn admin-uitnodigingen.
            $table->string('role')->default(UserRole::Admin->value)->after('competition_id');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('competition_id');
            $table->dropColumn('role');
        });
    }
};
