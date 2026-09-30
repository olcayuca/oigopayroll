<?php

namespace App\Actions\Companies;

use App\Enums\AuditEvent;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;
use App\Support\Audit;
use App\Validation\CompanyRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Create or update a company. Used by both the manual form and the Excel import.
 *
 * Authorization is the caller's job (CompanyPolicy); this enforces the business rules.
 */
class SaveCompany
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(Firm $firm, array $input, ?User $user = null): Company
    {
        if (! $firm->isActive()) {
            throw ValidationException::withMessages(['firm' => 'Şirket yalnızca aktif firmalara eklenebilir.']);
        }

        $data = $this->validate($input);

        $company = $firm->companies()->create([...$data, 'created_by' => $user?->id]);
        Audit::log(AuditEvent::CompanyCreated, "Şirket oluşturuldu: {$company->company_no} {$company->title}", $company);

        return $company;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Company $company, array $input): Company
    {
        $company->update($this->validate($input, $company));

        if ($company->wasChanged()) {
            Audit::log(AuditEvent::CompanyUpdated, "Şirket güncellendi: {$company->company_no} {$company->title}", $company, ['fields' => array_keys($company->getChanges())]);
        }

        return $company;
    }

    /**
     * Soft-delete a company that has no workplaces.
     */
    public function delete(Company $company): void
    {
        if ($company->workplaces()->exists()) {
            throw ValidationException::withMessages(['company' => 'İşyeri bulunan şirket silinemez; önce işyerlerini kaldırın.']);
        }

        $company->delete();
        Audit::log(AuditEvent::CompanyDeleted, "Şirket silindi: {$company->company_no} {$company->title}", $company);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $input, ?Company $ignore = null): array
    {
        $input = array_map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value, $input);

        return Validator::make($input, CompanyRules::rules($input, $ignore), [], CompanyRules::attributes())->validate();
    }
}
