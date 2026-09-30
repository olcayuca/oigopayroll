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
        // Payroll legal parameters with an effective date: a value applies from
        // effective_from until the next entry of the same key.
        Schema::create('legal_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64);
            $table->date('effective_from');
            $table->json('value');
            $table->string('source')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['key', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_parameters');
    }
};
