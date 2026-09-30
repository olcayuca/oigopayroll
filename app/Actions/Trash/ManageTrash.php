<?php

namespace App\Actions\Trash;

use App\Enums\AuditEvent;
use App\Enums\ScopeType;
use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Workplace;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Çöp kutusu: soft-deleted companies and workplaces.
 *
 * Restoring follows the hierarchy (a workplace needs its company back first). Purging is
 * permanent and admin-only; a company can only be purged once none of its workplaces remain.
 */
class ManageTrash
{
    public function restore(Company|Workplace $record): void
    {
        if ($record instanceof Workplace && Company::onlyTrashed()->whereKey($record->company_id)->exists()) {
            throw ValidationException::withMessages(['record' => 'Bu işyerinin şirketi de silinmiş; önce şirketi geri alın.']);
        }

        $record->restore();

        $record instanceof Company
            ? Audit::log(AuditEvent::CompanyRestored, "Şirket geri alındı: {$record->company_no} {$record->title}", $record)
            : Audit::log(AuditEvent::WorkplaceRestored, "İşyeri geri alındı: {$record->branch_name}", $record);
    }

    public function purge(Company|Workplace $record): void
    {
        if ($record instanceof Company && Workplace::withTrashed()->where('company_id', $record->id)->exists()) {
            throw ValidationException::withMessages(['record' => 'Şirkete ait silinmiş işyerleri var; önce onları kalıcı silin.']);
        }

        $label = $record instanceof Company ? "şirket {$record->company_no} {$record->title}" : "işyeri {$record->branch_name}";

        DB::transaction(function () use ($record) {
            AccessGrant::query()
                ->where('scope_type', $record instanceof Company ? ScopeType::Company : ScopeType::Workplace)
                ->where('scope_id', $record->id)
                ->delete();

            $record->forceDelete();
        });

        Audit::log(AuditEvent::RecordPurged, "Kalıcı olarak silindi: {$label}", null, [
            'type' => $record instanceof Company ? 'company' : 'workplace',
            'id' => $record->id,
        ]);
    }

    /**
     * Who deleted each record and when, from the audit log.
     *
     * @param  Collection<int, Company>|Collection<int, Workplace>  $records
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
            ->whereIn('event', [AuditEvent::CompanyDeleted, AuditEvent::WorkplaceDeleted])
            ->orderBy('id')
            ->get()
            ->keyBy('subject_id');
    }
}
