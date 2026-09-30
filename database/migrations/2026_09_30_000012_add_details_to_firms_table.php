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
        Schema::table('firms', function (Blueprint $table) {
            $table->string('title')->nullable()->after('name');
            // Unique when present: used to find a firm when granting it access to another firm.
            $table->string('tax_number', 11)->nullable()->unique()->after('title');
            $table->string('tax_office')->nullable()->after('tax_number');
            $table->string('contact_name')->nullable()->after('tax_office');
            $table->string('phone')->nullable()->after('contact_name');
            $table->string('email')->nullable()->after('phone');
            $table->text('address')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('firms', function (Blueprint $table) {
            $table->dropUnique(['tax_number']);
            $table->dropColumn(['title', 'tax_number', 'tax_office', 'contact_name', 'phone', 'email', 'address']);
        });
    }
};
