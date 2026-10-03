<?php

namespace App\Validation;

use App\Enums\CodeList;
use App\Enums\DefinitionType;
use App\Models\Definition;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\PayrollCode;
use App\Rules\Iban;
use App\Rules\Tckn;
use App\Support\EmployeeOptions;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Validation for manual and Excel personnel entry (same rules for both paths).
 * Required fields follow the red columns of the customer setup file (docs/KURULUM_DOSYASI.md §2).
 */
final class EmployeeRules
{
    /**
     * @param  array<string, mixed>  $input  Normalised input (EmployeeInput::normalize()).
     * @return array<string, list<mixed>>
     */
    public static function rules(array $input, Firm $firm, ?Employee $ignore = null): array
    {
        // Encrypted fields may be left blank on update when a value is already stored.
        $secret = fn (string $field) => $ignore !== null && filled($ignore->getAttributes()[$field] ?? null) && blank($input[$field] ?? null)
            ? 'sometimes' : 'required';
        $choice = fn (array $options) => Rule::in($options);
        $money = ['required', 'numeric', 'min:0', 'max:9999999999999'];

        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('firm_id', $firm->id)->whereNull('deleted_at')],
            'workplace_id' => ['required', 'integer', Rule::exists('workplaces', 'id')->whereNull('deleted_at')
                ->whereIn('company_id', $firm->companies()->pluck('id')->all())],
            'status' => ['sometimes', Rule::in(array_keys(Employee::STATUSES))],

            'registry_no' => ['required', 'string', 'max:50', Rule::unique('employees', 'registry_no')->where('firm_id', $firm->id)->ignore($ignore)],
            'tckn' => [$secret('tckn'), new Tckn],
            'tckn_hash' => ['nullable', Rule::unique('employees', 'tckn_hash')->where('firm_id', $firm->id)->ignore($ignore)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'second_last_name' => ['nullable', 'string', 'max:255'],
            'work_email' => ['nullable', 'email', 'max:255'],
            'personal_email' => ['required', 'email', 'max:255'],
            'mobile_phone' => ['required', 'string', 'max:30'],
            'work_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'province' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],

            'birth_date' => ['required', 'date', 'before:today'],
            'gender' => ['required', $choice(EmployeeOptions::GENDER)],
            'marital_status' => ['nullable', $choice(EmployeeOptions::MARITAL_STATUS)],
            'education' => ['nullable', $choice(EmployeeOptions::EDUCATION)],
            'graduation_field' => ['nullable', 'string', 'max:255'],
            'military_status' => ['nullable', $choice(EmployeeOptions::MILITARY)],

            'hire_date' => ['required', 'date', 'after:birth_date'],
            'seniority_date' => ['required', 'date'],
            'leave_base_date' => ['required', 'date'],
            'collar' => ['nullable', $choice(EmployeeOptions::COLLAR)],
            'upper_unit_id' => ['required', self::definition($firm, DefinitionType::UpperUnit)],
            'unit_id' => ['nullable', self::definition($firm, DefinitionType::Unit)],
            'job_family_id' => ['nullable', self::definition($firm, DefinitionType::JobFamily)],
            'duty_type' => ['nullable', $choice(EmployeeOptions::DUTY_TYPE)],
            'title_id' => ['required', self::definition($firm, DefinitionType::Title)],
            'position_id' => ['required', self::definition($firm, DefinitionType::Position)],
            'level_id' => ['nullable', self::definition($firm, DefinitionType::Level)],
            'leave_manager_registry_no' => ['required', 'string', 'max:50'],
            'functional_manager_registry_no' => ['nullable', 'string', 'max:50'],

            // Checked against Admin → Bordro Kodları → Meslek Kodları once that list is loaded.
            'occupation_code' => ['required', 'regex:/^\d{4}\.\d{2,3}$/', ...(self::occupations() === [] ? [] : [Rule::in(self::occupations())])],
            'insurance_branch' => ['required', $choice(EmployeeOptions::INSURANCE_BRANCH)],
            'sgk_status' => ['required', $choice(EmployeeOptions::SGK_STATUS)],
            'employment_type' => ['required', $choice(EmployeeOptions::EMPLOYMENT_TYPE)],
            'duty_code' => ['required', $choice(EmployeeOptions::DUTY_CODE)],
            'sgk_document_type' => ['required', Rule::in(self::documentTypes())],

            'bank_name' => ['required', 'string', 'max:255'],
            'bank_branch' => ['required', 'string', 'max:255'],
            'iban' => [$secret('iban'), new Iban],
            'account_no' => [$secret('account_no'), 'string', 'max:50'],

            'wage_period' => ['required', $choice(EmployeeOptions::WAGE_PERIOD)],
            'currency' => ['required', $choice(EmployeeOptions::CURRENCY)],
            'wage_type' => ['required', $choice(EmployeeOptions::WAGE_TYPE)],
            'wage' => [...$money, 'gt:0'],
            'is_minimum_wage' => ['required', 'boolean'],
            'minimum_wage_exemption' => ['required', 'boolean'],
            'bes_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'cumulative_tax_base' => $money,
            'tax_exemption_start_month' => ['required', 'integer', 'between:1,12'],
            'previous_sgk_base_1' => $money,
            'previous_sgk_base_2' => $money,
            'rnd_rate' => ['nullable', $choice(EmployeeOptions::RND_RATE)],

            'disability_degree' => ['nullable', $choice(EmployeeOptions::DISABILITY_DEGREE)],
            'disability_tax_relief' => ['nullable', 'boolean'],
            'disability_end_date' => ['nullable', 'date'],
            'cost_group_id' => ['nullable', self::definition($firm, DefinitionType::CostGroup)],
            'cost_group_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'work_model' => ['required', $choice(EmployeeOptions::WORK_MODEL)],
            'contract_type' => ['required', $choice(EmployeeOptions::CONTRACT_TYPE)],
            'is_shift_worker' => ['required', 'boolean'],
            'shift_start' => ['required', 'date_format:H:i'],
            'shift_end' => ['required', 'date_format:H:i'],
            'weekly_rest' => ['required', $choice(EmployeeOptions::WEEKLY_REST)],
            'remaining_leave_days' => ['required', 'numeric', 'min:0', 'max:999'],
        ];
    }

    /**
     * An existing definition of the firm, or a new one given by name ("yeni:<name>", see EmployeeInput).
     */
    private static function definition(Firm $firm, DefinitionType $type): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($firm, $type) {
            if (is_string($value) && str_starts_with($value, EmployeeInput::NEW_PREFIX) && strlen($value) > strlen(EmployeeInput::NEW_PREFIX)) {
                return;
            }

            if (! is_numeric($value) || ! Definition::query()->ofType($firm, $type)->whereKey((int) $value)->exists()) {
                $fail("Seçilen {$type->singular()} bu firmanın tanımlarında bulunamadı.");
            }
        };
    }

    /**
     * SGK belge türü codes (Admin → Bordro Kodları).
     *
     * @return list<string>
     */
    public static function documentTypes(): array
    {
        return once(fn () => array_values(array_map('strval', PayrollCode::query()->where('list', CodeList::DocumentTypes)->pluck('code')->all())));
    }

    /**
     * Active SGK occupation codes (Admin → Bordro Kodları); empty until the list is loaded.
     *
     * @return list<string>
     */
    public static function occupations(): array
    {
        return once(fn () => array_values(array_map('strval', PayrollCode::query()->where('list', CodeList::Occupations)->where('is_active', true)->pluck('code')->all())));
    }

    /**
     * Turkish attribute names for error messages.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'company_id' => 'Firma',
            'workplace_id' => 'İş Yeri Şube',
            'status' => 'Durum',
            'registry_no' => 'Sicil No',
            'tckn' => 'TC Kimlik No',
            'tckn_hash' => 'TC Kimlik No',
            'first_name' => 'Adı',
            'last_name' => 'Soyadı',
            'second_last_name' => 'İkinci Soyadı',
            'work_email' => 'Şirket E-posta',
            'personal_email' => 'Kişisel E-posta',
            'mobile_phone' => 'Cep Telefonu',
            'work_phone' => 'İş Telefonu',
            'address' => 'Oturulan Adres',
            'province' => 'İl',
            'district' => 'İlçe',
            'birth_date' => 'Doğum Tarihi',
            'gender' => 'Cinsiyet',
            'marital_status' => 'Medeni Hal',
            'education' => 'Eğitim Durumu',
            'graduation_field' => 'Mezuniyet Bölümü',
            'military_status' => 'Askerlik',
            'hire_date' => 'İşe Giriş Tarihi',
            'seniority_date' => 'Kıdeme Esas Tarihi',
            'leave_base_date' => 'İzne Esas Tarihi',
            'collar' => 'Yaka',
            'upper_unit_id' => 'Üst Birim',
            'unit_id' => 'Birim',
            'job_family_id' => 'İş Ailesi',
            'duty_type' => 'Görev Tipi',
            'title_id' => 'Unvan',
            'position_id' => 'Pozisyon',
            'level_id' => 'Seviye',
            'leave_manager_registry_no' => 'İzin Yönetici Sicil No',
            'functional_manager_registry_no' => 'Fonksiyonel Yönetici Sicil No',
            'occupation_code' => 'SGK Meslek Kodu',
            'insurance_branch' => 'Sigorta Kolu',
            'sgk_status' => 'SGK Statü',
            'employment_type' => 'Çalışan Tipi',
            'duty_code' => 'Görev Kodu',
            'sgk_document_type' => 'SGK Belge Türü',
            'bank_name' => 'Banka Adı',
            'bank_branch' => 'Banka Şube Adı',
            'iban' => 'IBAN',
            'account_no' => 'Hesap No',
            'wage_period' => 'Ücret Periyodu',
            'currency' => 'Para Birimi',
            'wage_type' => 'Ücret Tipi',
            'wage' => 'Ücret',
            'is_minimum_wage' => 'Asgari Ücretli',
            'minimum_wage_exemption' => 'Asgari Ücret Vergi İstisnasına Tabi',
            'bes_rate' => 'Otomatik BES Oranı',
            'cumulative_tax_base' => 'Kümülatif Gelir Vergisi Matrahı',
            'tax_exemption_start_month' => 'Vergi İstisnası Başlangıç Ayı',
            'previous_sgk_base_1' => 'Bir Önceki Dönem Devreden SGK Matrahı',
            'previous_sgk_base_2' => 'İki Önceki Dönem Devreden SGK Matrahı',
            'rnd_rate' => 'Ar-Ge İndirim Oranı',
            'disability_degree' => 'Engellilik Derecesi',
            'disability_tax_relief' => 'Engelli Gelir Vergisi İndirimi',
            'disability_end_date' => 'Engellilik Bitiş Tarihi',
            'cost_group_id' => 'Masraf Grubu',
            'cost_group_rate' => 'Masraf Grubu Oranı',
            'work_model' => 'Çalışma Modeli',
            'contract_type' => 'Sözleşme Türü',
            'is_shift_worker' => 'Vardiyalı Çalışma',
            'shift_start' => 'Mesai Başlangıç Saati',
            'shift_end' => 'Mesai Bitiş Saati',
            'weekly_rest' => 'Hafta Tatili',
            'remaining_leave_days' => 'Kalan Yıllık İzin Hakkı',
        ];
    }
}
