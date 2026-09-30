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
        // Official payroll code lists (SGK belge türleri, eksik gün nedenleri, işten çıkış kodları,
        // teşvik kanunları, meslek kodları, bankalar), one table keyed by list.
        Schema::create('payroll_codes', function (Blueprint $table) {
            $table->id();
            $table->string('list', 32);
            $table->string('code', 32);
            $table->string('name', 500);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['list', 'code']);
            $table->index(['list', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_codes');
    }
};
