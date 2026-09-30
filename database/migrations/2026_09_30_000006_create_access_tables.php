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
        // Optional, reusable permission bundles.
        Schema::create('permission_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->json('permissions');
            $table->timestamps();
        });

        // Which user may do what on which firm / company / workplace.
        Schema::create('access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type');
            $table->unsignedBigInteger('scope_id');
            $table->foreignId('permission_template_id')->nullable()->constrained()->nullOnDelete();
            $table->json('permissions')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'scope_type', 'scope_id']);
            $table->index(['scope_type', 'scope_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_grants');
        Schema::dropIfExists('permission_templates');
    }
};
