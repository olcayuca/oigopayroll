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
        // Duyurular: shown at the top of the panel to the chosen audience.
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('level', 16);
            $table->string('audience', 16);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });

        // Target firms when audience = firms.
        Schema::create('announcement_firm', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();

            $table->primary(['announcement_id', 'firm_id']);
        });

        Schema::create('announcement_dismissals', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('dismissed_at');

            $table->primary(['announcement_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_dismissals');
        Schema::dropIfExists('announcement_firm');
        Schema::dropIfExists('announcements');
    }
};
