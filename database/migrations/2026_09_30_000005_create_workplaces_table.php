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
        Schema::create('workplaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();

            // Temel bilgiler
            $table->string('workplace_no');
            $table->string('branch_name');
            $table->string('workplace_type');
            $table->string('workplace_kind');
            $table->string('title');
            $table->string('registration_type')->nullable();
            $table->string('tax_number', 11);
            $table->string('tax_office');
            $table->string('mersis_no', 16)->nullable();
            $table->foreignId('risk_class_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('hazard_class');
            $table->unsignedSmallInteger('labor_sector_id')->nullable();

            // Adres: il/ilçe are chosen from lists, with a free-text fallback.
            $table->unsignedSmallInteger('province_id')->nullable();
            $table->string('province_name')->nullable();
            $table->foreignId('district_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('district_name')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('street')->nullable();
            $table->string('outer_door_no')->nullable();
            $table->string('inner_door_no')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->text('address');

            // İletişim
            $table->string('phone')->nullable();
            $table->string('mobile_phone')->nullable();
            $table->string('email')->nullable();
            $table->string('kep_address')->nullable();
            $table->string('e_signature_officer')->nullable();

            // SGK
            $table->string('sgk_registry_no', 26)->nullable()->unique();
            $table->string('sgk_directorate')->nullable();
            $table->string('sgk_officer_name');
            $table->string('sgk_workplace_code');
            $table->string('ebildirge_officer_name');
            $table->date('opening_date');
            $table->date('closing_date')->nullable();
            $table->string('mahiyet_code')->nullable();
            $table->string('mahiyet_name')->nullable();

            // İŞKUR / TÜİK / Vergi
            $table->string('iskur_user_name')->nullable();
            $table->string('iskur_registry_no')->nullable();
            $table->string('tuik_user_full_name')->nullable();
            $table->string('tuik_username')->nullable();
            $table->string('tax_office_user_code')->nullable();

            // Encrypted at rest (Laravel `encrypted` cast), hence text columns.
            $table->text('sgk_declaration_username');
            $table->text('sgk_workplace_password');
            $table->text('sgk_system_password');
            $table->text('iskur_user_code')->nullable();
            $table->text('iskur_password')->nullable();
            $table->text('tuik_password')->nullable();
            $table->text('ebeyanname_password')->nullable();

            // Sendika / toplu iş sözleşmesi
            $table->boolean('has_union')->default(false);
            $table->string('union_name')->nullable();
            $table->date('cba_start_date')->nullable();
            $table->date('cba_end_date')->nullable();
            $table->date('cba_signed_date')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'workplace_no']);
            $table->foreign('province_id')->references('id')->on('provinces')->restrictOnDelete();
            $table->foreign('labor_sector_id')->references('id')->on('labor_sectors')->restrictOnDelete();
        });

        Schema::create('credential_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workplace_id')->constrained()->cascadeOnDelete();
            $table->string('field');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credential_access_logs');
        Schema::dropIfExists('workplaces');
    }
};
