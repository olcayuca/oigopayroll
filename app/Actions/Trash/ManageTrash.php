<?php

namespace App\Actions\Trash;

use App\Enums\AuditEvent;
use App\Enums\ScopeType;
use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Workplace;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Çöp kutusu: soft-deleted companies, workplaces and personnel.
 *
 * Restoring follows the hierarchy (a workplace needs its company back first, personnel their
 * workplace and company). Purging is permanent and admin-only; a company or workplace can only
 * be purged once nothing below it remains.
 */
class ManageTrash
{
    public function restore(Company|Workplace|Employee $record): void
    {
        if ($record instanceof Workplace && Company::onlyTrashed()->whereKey($record->company_id)->exists()) {
            throw ValidationException::withMessages(['record' => 'Bu işyerinin şirketi de silinmiş; önce şirketi geri alın.']);
        }

        if ($record instanceof Employee) {
            $workplace = Workplace::withTrashed()->find($record->workplace_id);

            if ($workplace === null || $workplace->trashed() || Company::onlyTrashed()->whereKey([$record->company_id, $workplace->company_id])->exists()) {
                throw ValidationException::withMessages(['record' => 'Personelin işyeri veya şirketi de silinmiş; önce onları geri alın.']);
            }
        }

        $record->restore();

        match (true) {
            $record instanceof Company => Audit::log(AuditEvent::CompanyRestored, "Şirket geri alındı: {$record->company_no} {$record->title}", $record),
            $record instanceof Workplace => Audit::log(AuditEvent::WorkplaceRestored, "İşyeri geri alındı: {$record->branch_name}", $record),
            default => Audit::log(AuditEvent::EmployeeRestored, "Personel geri alındı: {$record->registry_no} {$record->fullName()}", $record),
        };
    }

    public function purge(Company|Workplace|Employee $record): void
    {
        if ($record instanceof Company && Workplace::withTrashed()->where('company_id', $record->id)->exists()) {
            throw ValidationException::withMessages(['record' => 'Şirkete ait silinmiş işyerleri var; önce onları kalıcı silin.']);
        }

        if (! $record instanceof Employee && Employee::withTrashed()->where($record instanceof Company ? 'company_id' : 'workplace_id', $record->id)->exists()) {
            throw ValidationException::withMessages(['record' => 'Bu kayda bağlı personel var; önce personeli kalıcı silin.']);
        }

        [$type, $label] = match (true) {
            $record instanceof Company => ['company', "şirket {$record->company_no} {$record->title}"],
            $record instanceof Workplace => ['workplace', "işyeri {$record->branch_name}"],
            default => ['employee', "personel sicil {$record->registry_no}"],
        };

        DB::transaction(function () use ($record) {
            if (! $record instanceof Employee) {
                AccessGrant::query()
                    ->where('scope_type', $record instanceof Company ? ScopeType::Company : ScopeType::Workplace)
                    ->where('scope_id', $record->id)
                    ->delete();
            }

            $record->forceDelete();
        });

        Audit::log(AuditEvent::RecordPurged, "Kalıcı olarak silindi: {$label}", null, ['type' => $type, 'id' => $record->id]);
    }

    /**
     * Who deleted each record and when, from the audit log.
     *
     * @param  Collection<int, Company>|Collection<int, Workplace>|Collection<int, Employee>  $records
     * @return Collection<int, AuditLog> keyed by record id
     */
    public function deletions(Collection $records): Collection
    {
        $first = $records->first();

        if ($first === null) {
            return collect();
        }

        return AuditLog::query()->with('user')
            ->where('subject_type', $first->getMorphClass())
            ->whereIn('subject_id', $records->pluck('id'))
            ->whereIn('event', [AuditEvent::CompanyDeleted, AuditEvent::WorkplaceDeleted, AuditEvent::EmployeeDeleted])
            ->orderBy('id')
            ->get()
            ->keyBy('subject_id');
    }
}
