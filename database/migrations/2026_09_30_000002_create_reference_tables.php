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
        // İller: id is the plate code.
        Schema::create('provinces', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name')->unique();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('province_id');
            $table->string('name');

            $table->foreign('province_id')->references('id')->on('provinces')->cascadeOnDelete();
            $table->unique(['province_id', 'name']);
        });

        // ÇSGB işkolları (6356 sayılı Kanun): id is the işkolu number.
        Schema::create('labor_sectors', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name')->unique();
        });

        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('risk_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risk_classes');
        Schema::dropIfExists('sectors');
        Schema::dropIfExists('labor_sectors');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
    }
};
