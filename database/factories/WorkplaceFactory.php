<?php

namespace Database\Factories;

use App\Enums\HazardClass;
use App\Enums\WorkplaceKind;
use App\Enums\WorkplaceType;
use App\Models\Company;
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
        ];
    }
}
