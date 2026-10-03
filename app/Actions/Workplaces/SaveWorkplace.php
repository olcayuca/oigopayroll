<?php

namespace App\Actions\Workplaces;

use App\Enums\AuditEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\Workplace;
use App\Support\Audit;
use App\Support\AuditChanges;
use App\Validation\WorkplaceInput;
use App\Validation\WorkplaceRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Create or update a workplace. Used by both the manual form and the Excel import.
 *
 * Authorization is the caller's job (WorkplacePolicy); this enforces the business rules.
 */
class SaveWorkplace
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(Company $company, array $input, ?User $user = null): Workplace
    {
        if (! $company->firm->isActive()) {
            throw ValidationException::withMessages(['firm' => 'İşyeri yalnızca aktif firmaların şirketlerine eklenebilir.']);
        }

        $data = $this->validate($input, $company);

        $workplace = $company->workplaces()->create([...$data, 'created_by' => $user?->id]);
        Audit::log(AuditEvent::WorkplaceCreated, "İşyeri oluşturuldu: {$company->short_name} / {$workplace->branch_name}", $workplace, [
            'changes' => AuditChanges::created($workplace, ['workplace_no', 'branch_name', 'workplace_type', 'workplace_kind', 'tax_number',
                'sgk_registry_no', 'hazard_class', 'nace_code', 'labor_sector_id', 'province_id', 'province_name'], WorkplaceRules::attributes()),
        ]);

        return $workplace;
    }

    /**
     * Blank credential fields keep their stored values.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(Workplace $workplace, array $input): Workplace
    {
        foreach (Workplace::SECRET_FIELDS as $field) {
            if (array_key_exists($field, $input) && ($input[$field] === null || $input[$field] === '')) {
                unset($input[$field]);
            }
        }

        $before = AuditChanges::snapshot($workplace);
        $workplace->update($this->validate($input, $workplace->company, $workplace));

        if ($workplace->wasChanged()) {
            // Credentials appear masked ("değiştirildi"): their values never reach the log.
            Audit::log(AuditEvent::WorkplaceUpdated, "İşyeri güncellendi: {$workplace->branch_name}", $workplace, [
                'changes' => AuditChanges::between($workplace, $before, WorkplaceRules::attributes()),
            ]);
        }

        return $workplace;
    }

    /**
     * Soft-delete a workplace.
     */
    public function delete(Workplace $workplace): void
    {
        if (Employee::query()->where('workplace_id', $workplace->id)->exists()) {
            throw ValidationException::withMessages(['workplace' => 'Personeli bulunan işyeri silinemez; önce personeli taşıyın veya silin.']);
        }

        $workplace->delete();
        Audit::log(AuditEvent::WorkplaceDeleted, "İşyeri silindi: {$workplace->branch_name}", $workplace);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $input, Company $company, ?Workplace $ignore = null): array
    {
        $input = WorkplaceInput::normalize($input, $company);

        return Validator::make(
            $input,
            WorkplaceRules::rules($input, $company, $ignore, updating: $ignore !== null),
            [],
            WorkplaceRules::attributes(),
        )->validate();
    }
}
