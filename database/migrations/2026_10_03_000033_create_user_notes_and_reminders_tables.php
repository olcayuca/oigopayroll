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
        // Personal notes of a user (panel assistant shortcut), kept per active firm. Text is encrypted.
        Schema::create('user_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('firm_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['user_id', 'firm_id', 'updated_at']);
        });

        // Personal reminders: delivered once to the notification bell at remind_at.
        Schema::create('user_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('firm_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('note')->nullable();
            $table->dateTime('remind_at');
            $table->dateTime('notified_at')->nullable();
            $table->timestamps();

            $table->index(['notified_at', 'remind_at']);
            $table->index(['user_id', 'remind_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_reminders');
        Schema::dropIfExists('user_notes');
    }
};
