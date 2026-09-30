<?php

namespace App\Validation;

use App\Enums\HazardClass;
use App\Enums\RegistrationType;
use App\Enums\WorkplaceKind;
use App\Enums\WorkplaceType;
use App\Models\Company;
use App\Models\Workplace;
use App\Rules\TaxNumber;
use App\Rules\Tckn;
use Illuminate\Validation\Rule;

/**
 * Validation for manual and Excel workplace creation (same rules for both paths).
 */
final class WorkplaceRules
{
    /**
     * @param  array<string, mixed>  $input  Normalised input (see WorkplaceInput::normalize()).
     * @param  bool  $updating  When true, blank credentials mean "keep the stored value".
     * @return array<string, list<mixed>>
     */
    public static function rules(array $input, Company $company, ?Workplace $ignore = null, bool $updating = false): array
    {
        $isNaturalPerson = ($input['registration_type'] ?? null) === RegistrationType::Natural->value;
        $secret = $updating ? 'sometimes' : 'required';

        return [
            'workplace_no' => ['required', 'string', 'max:50',
                Rule::unique('workplaces', 'workplace_no')->where('company_id', $company->id)->ignore($ignore)],
            'branch_name' => ['required', 'string', 'max:255'],
            'workplace_type' => ['required', Rule::enum(WorkplaceType::class)],
            'workplace_kind' => ['required', Rule::enum(WorkplaceKind::class)],
            'title' => ['required', 'string', 'max:255'],
            'registration_type' => ['nullable', Rule::enum(RegistrationType::class)],
            'tax_number' => ['required', 'string', new TaxNumber(allowTckn: $isNaturalPerson)],
            'tax_office' => ['required', 'string', 'max:255'],
            'mersis_no' => ['nullable', 'digits:16'],
            'risk_class_id' => ['nullable', 'integer', Rule::exists('risk_classes', 'id')->where('is_active', true)],
            'hazard_class' => ['required', Rule::enum(HazardClass::class)],
            'labor_sector_id' => ['nullable', 'integer', Rule::exists('labor_sectors', 'id')],

            'province_id' => ['nullable', 'required_without:province_name', 'integer', Rule::exists('provinces', 'id')],
            'province_name' => ['nullable', 'required_without:province_id', 'string', 'max:100'],
            'district_id' => ['nullable', 'integer',
                Rule::exists('districts', 'id')->where('province_id', $input['province_id'] ?? 0)],
            'district_name' => ['nullable', 'required_without:district_id', 'string', 'max:100'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'outer_door_no' => ['nullable', 'string', 'max:20'],
            'inner_door_no' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['nullable', 'digits:5'],
            'address' => ['required', 'string', 'max:1000'],

            'phone' => ['nullable', 'string', 'max:20'],
            'mobile_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'kep_address' => ['nullable', 'email', 'max:255'],
            'e_signature_officer' => ['nullable', 'string', 'max:255'],

            'sgk_registry_no' => ['nullable', 'digits:26', Rule::unique('workplaces', 'sgk_registry_no')->ignore($ignore)],
            'sgk_directorate' => ['nullable', 'string', 'max:255'],
            'sgk_officer_name' => ['required', 'string', 'max:255'],
            'sgk_workplace_code' => ['required', 'string', 'max:50'],
            'ebildirge_officer_name' => ['required', 'string', 'max:255'],
            'opening_date' => ['required', 'date'],
            'closing_date' => ['nullable', 'date', 'after_or_equal:opening_date'],
            'mahiyet_code' => ['nullable', 'string', 'max:20'],
            'mahiyet_name' => ['nullable', 'string', 'max:255'],

            'sgk_declaration_username' => [$secret, new Tckn],
            'sgk_workplace_password' => [$secret, 'string', 'max:255'],
            'sgk_system_password' => [$secret, 'string', 'max:255'],

            'iskur_user_name' => ['nullable', 'string', 'max:255'],
            'iskur_user_code' => ['nullable', new Tckn],
            'iskur_password' => ['nullable', 'string', 'max:255'],
            'iskur_registry_no' => ['nullable', 'string', 'max:50'],
            'tuik_user_full_name' => ['nullable', 'string', 'max:255'],
            'tuik_username' => ['nullable', 'string', 'max:255'],
            'tuik_password' => ['nullable', 'string', 'max:255'],
            'tax_office_user_code' => ['nullable', 'string', 'max:50'],
            'ebeyanname_password' => ['nullable', 'string', 'max:255'],

            'has_union' => ['boolean'],
            'union_name' => ['nullable', 'required_if_accepted:has_union', 'string', 'max:255'],
            'cba_start_date' => ['nullable', 'date'],
            'cba_end_date' => ['nullable', 'date', 'after_or_equal:cba_start_date'],
            'cba_signed_date' => ['nullable', 'date'],
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
            'workplace_no' => 'İşyeri Numarası',
            'branch_name' => 'İşyeri Şube Adı',
            'workplace_type' => 'İşyeri Tipi',
            'workplace_kind' => 'İşyeri Türü',
            'title' => 'Ünvan',
            'registration_type' => 'Tescil Tipi',
            'tax_number' => 'Vergi Numarası',
            'tax_office' => 'Vergi Dairesi',
            'mersis_no' => 'MERSİS Numarası',
            'risk_class_id' => 'Risk Sınıfı',
            'hazard_class' => 'Tehlike Sınıfı',
            'labor_sector_id' => 'ÇSGB İşkolu',
            'province_id' => 'İl',
            'province_name' => 'İl',
            'district_id' => 'İlçe',
            'district_name' => 'İlçe',
            'neighborhood' => 'Mahalle',
            'street' => 'Cadde / Sokak / Bulvar',
            'outer_door_no' => 'Dış Kapı No',
            'inner_door_no' => 'İç Kapı No',
            'postal_code' => 'Posta Kodu',
            'address' => 'İşyeri Açık Adresi',
            'phone' => 'Telefon',
            'mobile_phone' => 'Cep Telefonu',
            'email' => 'E-Posta',
            'kep_address' => 'KEP Adresi',
            'e_signature_officer' => 'E-İmza Yetkilisi',
            'sgk_registry_no' => 'İşyeri SGK Sicil Numarası',
            'sgk_directorate' => 'Bağlı Bulunulan SGK Müdürlüğü',
            'sgk_officer_name' => 'SGK İşyeri Yetkilisi Adı Soyadı',
            'sgk_workplace_code' => 'SGK İşyeri Kodu',
            'ebildirge_officer_name' => 'e-Bildirge Yetkilisi Adı Soyadı',
            'opening_date' => 'İşyeri Açılış Tarihi',
            'closing_date' => 'İşyeri Kapanış Tarihi',
            'mahiyet_code' => 'Mahiyet Kodu',
            'mahiyet_name' => 'Mahiyet Adı',
            'sgk_declaration_username' => 'SGK Bildirge Kullanıcı Adı (TCKN)',
            'sgk_workplace_password' => 'SGK İşyeri Şifresi',
            'sgk_system_password' => 'SGK Sistem Şifresi',
            'iskur_user_name' => 'İŞKUR Kullanıcı Adı Soyadı',
            'iskur_user_code' => 'İŞKUR Kullanıcı Kodu (TCKN)',
            'iskur_password' => 'İŞKUR Şifresi',
            'iskur_registry_no' => 'İŞKUR Sicil Numarası',
            'tuik_user_full_name' => 'TÜİK Kullanıcı Adı Soyadı',
            'tuik_username' => 'TÜİK Kullanıcı Adı',
            'tuik_password' => 'TÜİK Şifresi',
            'tax_office_user_code' => 'Vergi Dairesi Kullanıcı Kodu',
            'ebeyanname_password' => 'e-Beyanname Şifresi',
            'has_union' => 'Sendikalı İşyeri',
            'union_name' => 'Sendika Adı',
            'cba_start_date' => 'TİS Başlangıç Tarihi',
            'cba_end_date' => 'TİS Bitiş Tarihi',
            'cba_signed_date' => 'TİS İmza Tarihi',
        ];
    }
}
