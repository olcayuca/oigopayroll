<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Client users belong to exactly one firm (their home firm).
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('firm_id')->nullable()->after('is_active')->constrained('firms')->nullOnDelete();
        });

        // Sub-firms remember the firm that opened them.
        Schema::table('firms', function (Blueprint $table) {
            $table->foreignId('parent_firm_id')->nullable()->after('id')->constrained('firms')->nullOnDelete();
        });

        // Existing client users: home firm = firm of their firm-level grant, else of any grant.
        $clients = DB::table('users')->where('type', 'client_user')->whereNull('firm_id')->pluck('id');

        foreach ($clients as $userId) {
            $grants = DB::table('access_grants')->where('user_id', $userId)->orderByRaw("scope_type = 'firm' desc")->orderBy('id')->get();

            foreach ($grants as $grant) {
                $firmId = match ($grant->scope_type) {
                    'firm' => $grant->scope_id,
                    'company' => DB::table('companies')->where('id', $grant->scope_id)->value('firm_id'),
                    default => DB::table('workplaces')->join('companies', 'companies.id', '=', 'workplaces.company_id')
                        ->where('workplaces.id', $grant->scope_id)->value('companies.firm_id'),
                };

                if ($firmId) {
                    DB::table('users')->where('id', $userId)->update(['firm_id' => $firmId]);
                    break;
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('firms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_firm_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('firm_id');
        });
    }
};
