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
        // Panel → Ayarlar: firm contact details, logo and firm-level settings (bordro varsayılanları, güvenlik).
        Schema::table('firms', function (Blueprint $table) {
            $table->string('kep_address')->nullable()->after('email');
            $table->string('website')->nullable()->after('kep_address');
            $table->string('mersis_no', 16)->nullable()->after('website');
            $table->string('logo_path')->nullable()->after('address');
            $table->json('settings')->nullable()->after('logo_path');
        });

        // Per user: e-mail / panel notification choices per category (App\Notifications\NotificationCategory).
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });

        Schema::table('firms', function (Blueprint $table) {
            $table->dropColumn(['kep_address', 'website', 'mersis_no', 'logo_path', 'settings']);
        });
    }
};
