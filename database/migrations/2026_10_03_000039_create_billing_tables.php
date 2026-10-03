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
        // Saved cards: only iyzico's tokens (encrypted) and display data. Card numbers never reach the system.
        Schema::create('firm_payment_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('iyzico');
            $table->text('card_user_key');
            $table->text('card_token');
            $table->string('last_four', 4)->nullable();
            $table->string('bin_number', 8)->nullable();
            $table->string('card_association', 40)->nullable(); // VISA, MASTER_CARD, TROY…
            $table->string('card_family', 40)->nullable();      // Bonus, World, Maximum…
            $table->boolean('is_default')->default(false);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Subscription invoices, issued per contract period.
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('firm_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30)->nullable()->unique();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('amount', 14, 2);       // KDV hariç
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('vat_amount', 14, 2);
            $table->decimal('total', 14, 2);        // KDV dahil, charged
            $table->string('currency', 3)->default('TRY');
            $table->string('status', 16);           // pending | paid | failed | void
            $table->date('due_on');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 20)->nullable(); // card_auto | card | transfer
            $table->foreignId('firm_payment_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_payment_id')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->date('next_attempt_on')->nullable();
            $table->string('failure_message')->nullable();
            $table->timestamps();

            $table->unique(['firm_contract_id', 'period_start']);
            $table->index(['status', 'due_on']);
        });

        // A hosted payment page session: iyzico posts the token back; we find the firm / invoice by it.
        Schema::create('billing_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 16); // invoice | card
            $table->string('conversation_id', 64)->unique();
            $table->string('token')->nullable()->unique();
            $table->string('status', 16)->default('started'); // started | completed | failed
            $table->string('message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_checkouts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('firm_payment_cards');
    }
};
