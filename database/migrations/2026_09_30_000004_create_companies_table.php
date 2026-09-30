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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->restrictOnDelete();

            // Zorunlu bilgiler
            $table->string('company_no')->unique();
            $table->string('title');
            $table->string('short_name');
            $table->string('company_type');
            $table->foreignId('sector_id')->constrained()->restrictOnDelete();
            $table->string('tax_number', 11)->index();
            $table->string('tax_office');

            // Diğer bilgiler
            $table->string('website')->nullable();
            $table->string('kep_address')->nullable();
            $table->string('trade_registry_no')->nullable();
            $table->string('mersis_no', 16)->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
