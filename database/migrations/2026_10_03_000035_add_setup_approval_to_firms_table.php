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
        // Kurulum onayı: the responsible payroll specialist signs off a complete setup.
        Schema::table('firms', function (Blueprint $table) {
            $table->timestamp('setup_approved_at')->nullable()->after('rejection_reason');
            $table->foreignId('setup_approved_by')->nullable()->after('setup_approved_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('firms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('setup_approved_by');
            $table->dropColumn('setup_approved_at');
        });
    }
};
