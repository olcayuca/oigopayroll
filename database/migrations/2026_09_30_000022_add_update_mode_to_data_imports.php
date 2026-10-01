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
        Schema::table('data_imports', function (Blueprint $table) {
            // Field keys of the columns found in the file: an update only touches these.
            $table->json('columns')->nullable()->after('file_errors');
            $table->unsignedInteger('created_rows')->default(0)->after('error_rows');
            $table->unsignedInteger('updated_rows')->default(0)->after('created_rows');
        });

        Schema::table('data_import_rows', function (Blueprint $table) {
            // create | update | unchanged (null for rows with errors / firm imports)
            $table->string('action', 16)->nullable()->after('errors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_import_rows', function (Blueprint $table) {
            $table->dropColumn('action');
        });

        Schema::table('data_imports', function (Blueprint $table) {
            $table->dropColumn(['columns', 'created_rows', 'updated_rows']);
        });
    }
};
