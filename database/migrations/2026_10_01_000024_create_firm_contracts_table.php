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
        // HRD service contracts with client firms (commercial data, admin only).
        Schema::create('firm_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->string('contract_no', 50)->unique();
            $table->string('title');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->unsignedSmallInteger('notice_days')->default(30);
            $table->string('fee_type', 30);
            $table->decimal('fee_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('TRY');
            $table->foreignId('document_id')->nullable()->constrained('firm_documents')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->date('terminated_on')->nullable();
            $table->string('termination_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['firm_id', 'ends_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firm_contracts');
    }
};
