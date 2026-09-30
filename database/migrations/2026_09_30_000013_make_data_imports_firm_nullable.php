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
        // Firm imports (admin) are not scoped to a firm.
        Schema::table('data_imports', function (Blueprint $table) {
            $table->foreignId('firm_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_imports', function (Blueprint $table) {
            $table->foreignId('firm_id')->nullable(false)->change();
        });
    }
};
