<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firm-level lists used on personnel records (Tanımlar): üst birim, birim, iş ailesi, unvan,
 * pozisyon, seviye, masraf grubu. See App\Enums\DefinitionType.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('code', 50);
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('definitions')->nullOnDelete();
            $table->json('extra')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['firm_id', 'type', 'code']);
            $table->index(['firm_id', 'type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('definitions');
    }
};
