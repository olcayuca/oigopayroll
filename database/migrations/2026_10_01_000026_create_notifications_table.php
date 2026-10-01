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
        // In-app notifications (Laravel database channel).
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Reminder bookkeeping: each state is announced once.
        Schema::table('firm_documents', function (Blueprint $table) {
            $table->string('expiry_notice', 16)->nullable()->after('valid_until');
        });

        Schema::table('firm_contracts', function (Blueprint $table) {
            $table->date('ending_notice_for')->nullable()->after('ends_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('firm_contracts', function (Blueprint $table) {
            $table->dropColumn('ending_notice_for');
        });

        Schema::table('firm_documents', function (Blueprint $table) {
            $table->dropColumn('expiry_notice');
        });

        Schema::dropIfExists('notifications');
    }
};
