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
        // Versioned KVKK texts (aydınlatma metni, açık rıza metni).
        Schema::create('policy_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->unsignedInteger('version');
            $table->string('title');
            $table->longText('body');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['type', 'version']);
        });

        // A user's decision on a document version (acknowledged / consent given or refused).
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('policy_document_id')->constrained()->cascadeOnDelete();
            $table->boolean('accepted');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('decided_at');
            $table->timestamp('revoked_at')->nullable();

            $table->unique(['user_id', 'policy_document_id']);
        });

        // Data subject applications (KVKK md. 11).
        Schema::create('kvkk_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requester_name');
            $table->string('requester_email');
            $table->string('type', 32);
            $table->string('status', 16)->index();
            $table->text('message');
            $table->text('response')->nullable();
            $table->date('due_at');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kvkk_requests');
        Schema::dropIfExists('consents');
        Schema::dropIfExists('policy_documents');
    }
};
