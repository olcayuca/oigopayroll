<?php

namespace App\Validation;

use App\Enums\CompanyType;
use App\Models\Company;
use App\Rules\TaxNumber;
use Illuminate\Validation\Rule;

/**
 * Validation for manual and Excel company creation (same rules for both paths).
 */
final class CompanyRules
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, list<mixed>>
     */
    public static function rules(array $input, ?Company $ignore = null): array
    {
        $isSoleProprietorship = ($input['company_type'] ?? null) === CompanyType::SoleProprietorship->value;

        return [
            'company_no' => ['required', 'string', 'max:50', Rule::unique('companies', 'company_no')->ignore($ignore)],
            'title' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:100'],
            'company_type' => ['required', Rule::enum(CompanyType::class)],
            'sector_id' => ['required', 'integer', Rule::exists('sectors', 'id')->where('is_active', true)],
            'tax_number' => ['required', 'string', new TaxNumber(allowTckn: $isSoleProprietorship)],
            'tax_office' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'kep_address' => ['nullable', 'email', 'max:255'],
            'trade_registry_no' => ['nullable', 'string', 'max:50'],
            'mersis_no' => ['nullable', 'digits:16'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Turkish attribute names for error messages.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'company_no' => 'Şirket Numarası',
            'title' => 'Şirket Adı / Unvanı',
            'short_name' => 'Şirket Kısa Adı',
            'company_type' => 'Şirket Tipi',
            'sector_id' => 'Şirket Sektörü',
            'tax_number' => 'Vergi Numarası',
            'tax_office' => 'Vergi Dairesi',
            'website' => 'Web Adresi',
            'kep_address' => 'KEP Adresi',
            'trade_registry_no' => 'Ticaret Sicil Numarası',
            'mersis_no' => 'MERSİS Numarası',
            'phone' => 'Telefon',
            'address' => 'Adres',
        ];
    }
}
