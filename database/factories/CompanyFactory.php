<?php

namespace Database\Factories;

use App\Enums\CompanyType;
use App\Models\Company;
use App\Models\Firm;
use App\Models\Sector;
use App\Support\TurkishIdentifiers;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->company();

        return [
            'firm_id' => Firm::factory(),
            'company_no' => (string) fake()->unique()->numberBetween(1000, 999999),
            'title' => $title,
            'short_name' => mb_substr($title, 0, 50),
            'company_type' => CompanyType::JointStock,
            'sector_id' => fn () => Sector::query()->value('id') ?? Sector::create(['name' => 'Diğer'])->id,
            'tax_number' => TurkishIdentifiers::makeVkn(fake()->numerify('#########')),
            'tax_office' => 'Kadıköy',
        ];
    }
}
