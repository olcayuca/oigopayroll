<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personel — the "Personel Bilgileri" sheet of the customer setup file (docs/KURULUM_DOSYASI.md §2).
 * Choice fields store their Turkish label (App\Support\EmployeeOptions). TCKN, IBAN and account
 * number are encrypted; tckn_hash keeps the TCKN unique per firm without decrypting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();      // Firma
            $table->foreignId('workplace_id')->constrained()->restrictOnDelete();    // SGK Firma + İş yeri şube
            $table->string('status', 20)->default('active')->index();

            // Kimlik & iletişim
            $table->string('registry_no', 50);
            $table->text('tckn');
            $table->string('tckn_hash', 64);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('second_last_name')->nullable();
            $table->string('work_email')->nullable();
            $table->string('personal_email');
            $table->string('mobile_phone', 30);
            $table->string('work_phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();

            // Kişisel
            $table->date('birth_date');
            $table->string('gender', 20);
            $table->string('marital_status', 20)->nullable();
            $table->string('education', 40)->nullable();
            $table->string('graduation_field')->nullable();
            $table->string('military_status', 20)->nullable();

            // İstihdam & organizasyon
            $table->date('hire_date');
            $table->date('seniority_date');
            $table->date('leave_base_date');
            $table->string('collar', 20)->nullable();
            $table->foreignId('job_family_id')->nullable()->constrained('definitions')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('definitions')->nullOnDelete();
            $table->foreignId('upper_unit_id')->nullable()->constrained('definitions')->nullOnDelete();
            $table->string('duty_type', 20)->nullable();
            $table->foreignId('title_id')->nullable()->constrained('definitions')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('definitions')->nullOnDelete();
            $table->foreignId('level_id')->nullable()->constrained('definitions')->nullOnDelete();
            $table->string('leave_manager_registry_no', 50)->nullable();
            $table->string('functional_manager_registry_no', 50)->nullable();

            // SGK
            $table->string('occupation_code', 20);
            $table->string('insurance_branch', 60);
            $table->string('sgk_status', 20);
            $table->string('employment_type', 30);
            $table->string('duty_code', 60);
            $table->string('sgk_document_type', 5);

            // Banka
            $table->string('bank_name');
            $table->string('bank_branch');
            $table->text('iban');
            $table->text('account_no');

            // Ücret & bordro
            $table->string('wage_period', 20);
            $table->string('currency', 10);
            $table->string('wage_type', 10);
            $table->decimal('wage', 15, 2);
            $table->boolean('is_minimum_wage');
            $table->boolean('minimum_wage_exemption');
            $table->decimal('bes_rate', 5, 2);
            $table->decimal('cumulative_tax_base', 15, 2);
            $table->unsignedTinyInteger('tax_exemption_start_month');
            $table->decimal('previous_sgk_base_1', 15, 2);
            $table->decimal('previous_sgk_base_2', 15, 2);
            $table->string('rnd_rate', 20)->nullable();

            // Engellilik & masraf
            $table->string('disability_degree', 20)->nullable();
            $table->boolean('disability_tax_relief')->nullable();
            $table->date('disability_end_date')->nullable();
            $table->foreignId('cost_group_id')->nullable()->constrained('definitions')->nullOnDelete();
            $table->decimal('cost_group_rate', 5, 2)->nullable();

            // Çalışma düzeni
            $table->string('work_model', 30);
            $table->string('contract_type', 30);
            $table->boolean('is_shift_worker');
            $table->time('shift_start');
            $table->time('shift_end');
            $table->string('weekly_rest', 60);
            $table->decimal('remaining_leave_days', 6, 2);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firm_id', 'registry_no']);
            $table->unique(['firm_id', 'tckn_hash']);
            $table->index(['workplace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
