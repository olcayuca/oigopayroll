<?php

namespace Tests\Feature\Payroll;

use App\Enums\CompanyType;
use App\Enums\HazardClass;
use App\Enums\WorkplaceKind;
use App\Enums\WorkplaceType;
use App\Models\District;
use App\Models\Sector;
use App\Support\TurkishIdentifiers;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class PayrollTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        District::create(['province_id' => 34, 'name' => 'Kadıköy']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function companyInput(array $overrides = []): array
    {
        return [
            'company_no' => '1001',
            'title' => 'Örnek Teknoloji A.Ş.',
            'short_name' => 'Örnek Teknoloji',
            'company_type' => CompanyType::JointStock->value,
            'sector_id' => Sector::where('name', 'Bilgi Teknolojileri')->value('id'),
            'tax_number' => TurkishIdentifiers::makeVkn('123456789'),
            'tax_office' => 'Kadıköy',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function workplaceInput(array $overrides = []): array
    {
        return [
            'workplace_no' => '1',
            'branch_name' => 'Merkez',
            'workplace_type' => WorkplaceType::Headquarters->value,
            'workplace_kind' => WorkplaceKind::Normal->value,
            'title' => 'Örnek Teknoloji A.Ş.',
            'tax_number' => TurkishIdentifiers::makeVkn('123456789'),
            'tax_office' => 'Kadıköy',
            'hazard_class' => HazardClass::Low->value,
            'province_name' => 'istanbul',
            'district_name' => 'KADIKÖY',
            'address' => 'Caferağa Mah. Moda Cad. No:1 Kadıköy/İstanbul',
            'sgk_officer_name' => 'Ayşe Yılmaz',
            'sgk_workplace_code' => '123456',
            'ebildirge_officer_name' => 'Ayşe Yılmaz',
            'opening_date' => '2020-01-15',
            'sgk_declaration_username' => '10000000146',
            'sgk_workplace_password' => 'isyeri-sifre',
            'sgk_system_password' => 'sistem-sifre',
            ...$overrides,
        ];
    }
}
