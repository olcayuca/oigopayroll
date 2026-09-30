<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the starter kit's Teams feature. Tenancy is Firm → Company → Workplace
 * with AccessGrant permissions, and each portal lives on its own subdomain.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'current_team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('current_team_id');
            });
        }

        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally one-way: the Teams feature has been removed from the codebase.
    }
};
