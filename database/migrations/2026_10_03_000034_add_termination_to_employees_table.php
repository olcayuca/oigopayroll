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
        // İşten çıkış: last working day, SGK işten ayrılış kodu (Admin → Bordro Kodları), note.
        Schema::table('employees', function (Blueprint $table) {
            $table->date('termination_date')->nullable()->after('status');
            $table->string('termination_code', 2)->nullable()->after('termination_date');
            $table->text('termination_note')->nullable()->after('termination_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['termination_date', 'termination_code', 'termination_note']);
        });
    }
};
