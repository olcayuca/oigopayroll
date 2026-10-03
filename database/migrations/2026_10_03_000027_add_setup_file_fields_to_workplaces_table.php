<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields of the customer's "KURULUM DOSYASI.xlsx" (Firma Bilgileri sheet) that were missing.
 * Nullable in the database for existing rows; WorkplaceRules makes them required for new records.
 * Credentials are text columns holding encrypted values (Workplace::SECRET_FIELDS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workplaces', function (Blueprint $table) {
            $table->string('nace_code', 20)->nullable()->after('labor_sector_id');
            $table->text('sgk_username')->nullable()->after('sgk_declaration_username');
            $table->string('dvd_username')->nullable()->after('tax_office_user_code');
            $table->text('dvd_password')->nullable()->after('dvd_username');
            $table->text('dvd_passphrase')->nullable()->after('dvd_password');
            $table->string('police_email')->nullable()->after('ebeyanname_password');
            $table->text('police_password')->nullable()->after('police_email');
            $table->string('bes_company_name')->nullable()->after('police_password');
            $table->string('bes_username')->nullable()->after('bes_company_name');
            $table->text('bes_password')->nullable()->after('bes_username');

            // Not in the setup file; optional from now on.
            $table->date('opening_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('workplaces', function (Blueprint $table) {
            $table->dropColumn([
                'nace_code', 'sgk_username', 'dvd_username', 'dvd_password', 'dvd_passphrase',
                'police_email', 'police_password', 'bes_company_name', 'bes_username', 'bes_password',
            ]);
        });
    }
};
