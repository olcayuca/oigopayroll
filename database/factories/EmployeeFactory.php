<?php

namespace Database\Factories;

use App\Enums\DefinitionType;
use App\Models\Definition;
use App\Models\Employee;
use App\Models\Workplace;
use App\Rules\Iban;
use App\Support\TurkishIdentifiers;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * A complete record (every required column of the setup file filled).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tckn = TurkishIdentifiers::makeTckn(fake()->unique()->numerify('1########'));

        return [
            'workplace_id' => Workplace::factory(),
            'company_id' => fn (array $attributes) => Workplace::find($attributes['workplace_id'])?->company_id,
            'firm_id' => fn (array $attributes) => Workplace::find($attributes['workplace_id'])?->company?->firm_id,
            'status' => Employee::ACTIVE,
            'registry_no' => (string) fake()->unique()->numberBetween(1000, 999999),
            'tckn' => $tckn,
            'tckn_hash' => Employee::hashTckn($tckn),
            'first_name' => fake('tr_TR')->firstName(),
            'last_name' => fake('tr_TR')->lastName(),
            'personal_email' => fake()->unique()->safeEmail(),
            'mobile_phone' => '+90 532 '.fake()->numerify('### ## ##'),
            'birth_date' => fake()->dateTimeBetween('-55 years', '-20 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['Kadın', 'Erkek']),
            'hire_date' => '2022-03-01',
            'seniority_date' => '2022-03-01',
            'leave_base_date' => '2022-03-01',
            'upper_unit_id' => fn (array $attributes) => $this->definitionFor($attributes['firm_id'], DefinitionType::UpperUnit, 'Genel Müdürlük'),
            'title_id' => fn (array $attributes) => $this->definitionFor($attributes['firm_id'], DefinitionType::Title, 'Uzman'),
            'position_id' => fn (array $attributes) => $this->definitionFor($attributes['firm_id'], DefinitionType::Position, 'Muhasebe Uzmanı'),
            'leave_manager_registry_no' => '1001',
            'occupation_code' => '2411.12',
            'insurance_branch' => 'Tüm Sigorta Kolları (Zorunlu)',
            'sgk_status' => 'Normal',
            'employment_type' => 'Belirsiz Süreli',
            'duty_code' => 'İşçi',
            'sgk_document_type' => '01',
            'bank_name' => 'Garanti BBVA',
            'bank_branch' => 'Ataşehir',
            'iban' => Iban::make('00062'.'0'.fake()->numerify('################')),
            'account_no' => fake()->numerify('#######'),
            'wage_period' => 'Aylık',
            'currency' => 'TRY',
            'wage_type' => 'Brüt',
            'wage' => fake()->randomElement([33030, 45000, 54800, 61200, 82400]),
            'is_minimum_wage' => false,
            'minimum_wage_exemption' => true,
            'bes_rate' => 3,
            'cumulative_tax_base' => 0,
            'tax_exemption_start_month' => 1,
            'previous_sgk_base_1' => 0,
            'previous_sgk_base_2' => 0,
            'work_model' => 'Hibrit',
            'contract_type' => 'Tam Zamanlı',
            'is_shift_worker' => false,
            'shift_start' => '09:00',
            'shift_end' => '18:00',
            'weekly_rest' => 'Cumartesi & Pazar',
            'remaining_leave_days' => 14,
        ];
    }

    private function definitionFor(?int $firmId, DefinitionType $type, string $name): ?int
    {
        if ($firmId === null) {
            return null;
        }

        return Definition::query()->firstOrCreate(
            ['firm_id' => $firmId, 'type' => $type, 'name' => $name],
            ['code' => mb_strtoupper(mb_substr(str_replace(' ', '', $name), 0, 6))],
        )->id;
    }
}
