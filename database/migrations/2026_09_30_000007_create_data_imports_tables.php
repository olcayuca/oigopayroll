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
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('status')->index();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->json('file_errors')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('data_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            // Encrypted: workplace rows carry SGK / İŞKUR passwords.
            $table->longText('data');
            $table->json('errors')->nullable();
            $table->nullableMorphs('created_record');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_import_rows');
        Schema::dropIfExists('data_imports');
    }
};
