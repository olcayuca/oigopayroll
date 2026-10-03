<?php

namespace Database\Factories;

use App\Enums\HazardClass;
use App\Enums\WorkplaceKind;
use App\Enums\WorkplaceType;
use App\Models\Company;
use App\Models\LaborSector;
use App\Models\Workplace;
use App\Support\TurkishIdentifiers;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workplace>
 */
class WorkplaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'workplace_no' => (string) fake()->unique()->numberBetween(1, 99999),
            'branch_name' => 'Merkez',
            'workplace_type' => WorkplaceType::Headquarters,
            'workplace_kind' => WorkplaceKind::Normal,
            'title' => fake()->company(),
            'tax_number' => TurkishIdentifiers::makeVkn(fake()->numerify('#########')),
            'tax_office' => 'Kadıköy',
            'hazard_class' => HazardClass::Low,
            'province_name' => 'İstanbul',
            'district_name' => 'Kadıköy',
            'address' => fake()->address(),
            'sgk_officer_name' => fake()->name(),
            'sgk_workplace_code' => fake()->numerify('######'),
            'ebildirge_officer_name' => fake()->name(),
            'opening_date' => fake()->date(),
            'sgk_declaration_username' => TurkishIdentifiers::makeTckn(fake()->numerify('1########')),
            'sgk_workplace_password' => fake()->password(),
            'sgk_system_password' => fake()->password(),

            // Remaining columns of the customer setup file (Workplace::SETUP_FIELDS)
            'nace_code' => '62.01.01',
            'labor_sector_id' => fn () => LaborSector::query()->value('id') ?? LaborSector::create(['id' => 20, 'name' => 'Genel İşler'])->id,
            'sgk_registry_no' => fake()->unique()->numerify(str_repeat('#', 26)),
            'sgk_directorate' => 'Kadıköy SGM',
            'sgk_username' => TurkishIdentifiers::makeTckn(fake()->numerify('1########')),
            'iskur_user_name' => fake()->name(),
            'iskur_user_code' => TurkishIdentifiers::makeTckn(fake()->numerify('1########')),
            'iskur_password' => fake()->password(),
            'iskur_registry_no' => fake()->numerify('34-#######'),
            'tax_office_user_code' => fake()->numerify('##########'),
            'dvd_username' => fake()->userName(),
            'dvd_password' => fake()->password(),
            'dvd_passphrase' => fake()->password(),
            'ebeyanname_password' => fake()->password(),
            'police_email' => fake()->safeEmail(),
            'police_password' => fake()->password(),
            'bes_company_name' => 'Anadolu Hayat Emeklilik',
            'bes_username' => fake()->userName(),
            'bes_password' => fake()->password(),
        ];
    }

    /**
     * Only the columns that were required before the setup file: the setup is incomplete.
     */
    public function incompleteSetup(): static
    {
        return $this->state(fn () => array_fill_keys([
            'nace_code', 'labor_sector_id', 'sgk_registry_no', 'sgk_directorate', 'sgk_username', 'iskur_user_name', 'iskur_user_code',
            'iskur_password', 'iskur_registry_no', 'tax_office_user_code', 'dvd_username', 'dvd_password', 'dvd_passphrase',
            'ebeyanname_password', 'police_email', 'police_password', 'bes_company_name', 'bes_username', 'bes_password',
        ], null));
    }
}
