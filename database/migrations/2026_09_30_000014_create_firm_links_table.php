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
        // "Manager firm may work on managed firm" (e.g. an accounting firm or a holding).
        Schema::create('firm_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manager_firm_id')->constrained('firms')->cascadeOnDelete();
            $table->foreignId('managed_firm_id')->constrained('firms')->cascadeOnDelete();
            $table->json('permissions');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['manager_firm_id', 'managed_firm_id']);
            $table->index('managed_firm_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firm_links');
    }
};
