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
            // remaining columns of the customer setup file
            'nace_code' => '62.01.01',
            'labor_sector_id' => 20,
            'sgk_registry_no' => str_repeat('6', 26),
            'sgk_directorate' => 'Kadıköy SGM',
            'sgk_username' => '10000000146',
            'iskur_user_name' => 'Ayşe Yılmaz',
            'iskur_user_code' => '10000000146',
            'iskur_password' => 'iskur-sifre',
            'iskur_registry_no' => '34-1029384',
            'tax_office_user_code' => '1234567890',
            'dvd_username' => 'ornekteknoloji',
            'dvd_password' => 'dvd-sifre',
            'dvd_passphrase' => 'dvd-parola',
            'ebeyanname_password' => 'ebeyan-sifre',
            'police_email' => 'bildirim@ornek.com.tr',
            'police_password' => 'emniyet-sifre',
            'bes_company_name' => 'Anadolu Hayat Emeklilik',
            'bes_username' => 'ornek.bes',
            'bes_password' => 'bes-sifre',
            ...$overrides,
        ];
    }
}
