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
        // Duyurular page (prototype 34-duyurular): category, pinning, a call-to-action link, notification on publish.
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('category', 24)->default('Sistem')->after('level');
            $table->boolean('pinned')->default(false)->after('category');
            $table->string('link_label', 60)->nullable()->after('body');
            $table->string('link_url')->nullable()->after('link_label');
            $table->boolean('notify')->default(false)->after('ends_at');
            $table->timestamp('notified_at')->nullable()->after('notify');
        });

        // Okundu bilgisi per user (separate from "do not show in the top strip again").
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->primary(['announcement_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['category', 'pinned', 'link_label', 'link_url', 'notify', 'notified_at']);
        });
    }
};
