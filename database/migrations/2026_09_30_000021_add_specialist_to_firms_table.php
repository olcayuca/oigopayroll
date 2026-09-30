<?php

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
        Schema::table('firms', function (Blueprint $table) {
            // Sorumlu bordro uzmanı (HRD). Backup specialists work through ordinary access grants.
            $table->foreignId('specialist_id')->nullable()->after('parent_firm_id')->constrained('users')->nullOnDelete();
            $table->timestamp('specialist_assigned_at')->nullable()->after('specialist_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('firms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialist_id');
            $table->dropColumn('specialist_assigned_at');
        });
    }
};
