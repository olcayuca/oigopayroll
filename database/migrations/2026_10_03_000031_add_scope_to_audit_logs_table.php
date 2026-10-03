<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\Workplace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * İşlem geçmişi per firm / company (şirket) / workplace (şube): the scope of every audit record,
 * resolved when it is written (App\Support\AuditScope). No foreign keys — the trail must outlive
 * purged records. Existing rows are back-filled from their subject.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('firm_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('company_id')->nullable()->after('firm_id');
            $table->unsignedBigInteger('workplace_id')->nullable()->after('company_id');

            $table->index(['firm_id', 'created_at']);
            $table->index(['company_id', 'created_at']);
            $table->index(['workplace_id', 'created_at']);
        });

        $morph = fn (string $class) => (new $class)->getMorphClass();

        DB::table('audit_logs')->where('subject_type', $morph(Firm::class))
            ->update(['firm_id' => DB::raw('subject_id')]);

        DB::table('audit_logs')->where('subject_type', $morph(Company::class))->orderBy('id')
            ->each(function (object $log) {
                $firmId = DB::table('companies')->where('id', $log->subject_id)->value('firm_id');
                DB::table('audit_logs')->where('id', $log->id)->update(['firm_id' => $firmId, 'company_id' => $log->subject_id]);
            });

        DB::table('audit_logs')->where('subject_type', $morph(Workplace::class))->orderBy('id')
            ->each(function (object $log) {
                $companyId = DB::table('workplaces')->where('id', $log->subject_id)->value('company_id');
                $firmId = $companyId ? DB::table('companies')->where('id', $companyId)->value('firm_id') : null;
                DB::table('audit_logs')->where('id', $log->id)->update(['firm_id' => $firmId, 'company_id' => $companyId, 'workplace_id' => $log->subject_id]);
            });

        DB::table('audit_logs')->where('subject_type', $morph(Employee::class))->orderBy('id')
            ->each(function (object $log) {
                $employee = DB::table('employees')->where('id', $log->subject_id)->first(['firm_id', 'company_id', 'workplace_id']);
                if ($employee) {
                    DB::table('audit_logs')->where('id', $log->id)->update((array) $employee);
                }
            });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['firm_id', 'created_at']);
            $table->dropIndex(['company_id', 'created_at']);
            $table->dropIndex(['workplace_id', 'created_at']);
            $table->dropColumn(['firm_id', 'company_id', 'workplace_id']);
        });
    }
};
