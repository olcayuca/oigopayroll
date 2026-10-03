<?php

namespace Database\Seeders;

use App\Actions\Access\GrantAccess;
use App\Enums\CompanyType;
use App\Enums\DefinitionType;
use App\Enums\HazardClass;
use App\Enums\Permission;
use App\Enums\WorkplaceType;
use App\Models\Company;
use App\Models\Definition;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use App\Support\Text;
use App\Support\TurkishIdentifiers;
use Illuminate\Database\Seeder;

/**
 * Local-only demo data for designing / trying the panel:
 * firm "Oigo Grup" with three companies and their workplaces, and a firm owner.
 *
 *   php artisan db:seed --class=PanelDemoSeeder
 *
 * Sign in on panel.<domain> as demo@oigo.test / password (UserFactory default).
 */
class PanelDemoSeeder extends Seeder
{
    public function run(GrantAccess $grantAccess): void
    {
        if (! app()->isLocal()) {
            return;
        }

        if (User::where('email', 'demo@oigo.test')->exists()) {
            $this->seedEmployees();

            return;
        }

        $firm = Firm::factory()->create([
            'name' => 'Oigo Grup',
            'title' => 'Oigo Grup Holding A.Ş.',
            'tax_number' => TurkishIdentifiers::makeVkn('634012398'),
            'tax_office' => 'Kozyatağı',
        ]);

        $owner = User::factory()->create([
            'name' => 'Selin Korkmaz',
            'email' => 'demo@oigo.test',
            'firm_id' => $firm->id,
        ]);
        $grantAccess->handle($owner, $firm, Permission::firmOwnerDefaults());

        $companies = [
            ['Oigo Yazılım A.Ş.', CompanyType::JointStock, [['Merkez Ofis', WorkplaceType::Headquarters, 'İstanbul', 'Ataşehir', HazardClass::Low], ['Ankara', WorkplaceType::Branch, 'Ankara', 'Çankaya', HazardClass::Low], ['İzmir', WorkplaceType::Branch, 'İzmir', 'Bayraklı', HazardClass::Low]]],
            ['Oigo Lojistik Ltd. Şti.', CompanyType::Limited, [['Merkez Depo', WorkplaceType::Headquarters, 'Kocaeli', 'Gebze', HazardClass::Medium]]],
            ['Oigo Gıda San. ve Tic. A.Ş.', CompanyType::JointStock, [['Bursa Fabrika', WorkplaceType::Headquarters, 'Bursa', 'Nilüfer', HazardClass::High]]],
            ['Oigo Enerji A.Ş.', CompanyType::JointStock, []],
        ];

        foreach ($companies as $index => [$title, $type, $workplaces]) {
            $company = Company::factory()->for($firm)->create([
                'company_no' => (string) (101 + $index),
                'title' => $title,
                'short_name' => $title,
                'company_type' => $type,
                'created_by' => $owner->id,
            ]);

            foreach ($workplaces as $no => [$branch, $workplaceType, $province, $district, $hazard]) {
                Workplace::factory()->for($company)->create([
                    'workplace_no' => sprintf('%03d', $no + 1),
                    'branch_name' => $branch,
                    'workplace_type' => $workplaceType,
                    'title' => $title,
                    'province_name' => $province,
                    'district_name' => $district,
                    'hazard_class' => $hazard,
                ]);
            }
        }

        $this->seedEmployees();
    }

    /**
     * A few personnel records (one with missing bank data) and the organisation definitions they use.
     */
    private function seedEmployees(): void
    {
        $firm = Firm::where('name', 'Oigo Grup')->first();

        if ($firm === null || Employee::where('firm_id', $firm->id)->exists()) {
            return;
        }

        $workplaces = Workplace::whereHas('company', fn ($query) => $query->where('firm_id', $firm->id))->orderBy('id')->get();
        $people = [
            ['1001', 'Ayşe', 'Yıldız', 'Kadın', 'Müdür', 'İnsan Kaynakları Müdürü', 82400],
            ['1002', 'Mehmet', 'Kaya', 'Erkek', 'Uzman', 'Muhasebe Uzmanı', 54800],
            ['1003', 'Elif', 'Demir', 'Kadın', 'Yönetici', 'Satış Yöneticisi', 61200],
            ['1004', 'Burak', 'Şahin', 'Erkek', 'Uzman', 'Operasyon Uzmanı', 39500],
            ['1005', 'Zeynep', 'Arslan', 'Kadın', 'Uzman', 'Yazılım Geliştirici', 78900],
            ['2001', 'Can', 'Öztürk', 'Erkek', 'Uzman', 'Depo Sorumlusu', 34200],
        ];

        foreach ($people as $index => [$registryNo, $first, $last, $gender, $title, $position, $wage]) {
            $workplace = $workplaces[$index % max(1, $workplaces->count())];
            $definition = fn (DefinitionType $type, string $name) => Definition::firstOrCreate(
                ['firm_id' => $firm->id, 'type' => $type, 'name' => $name],
                ['code' => mb_strtoupper(mb_substr(Text::key($name), 0, 6))],
            )->id;

            Employee::factory()->create([
                'workplace_id' => $workplace->id, 'company_id' => $workplace->company_id, 'firm_id' => $firm->id,
                'registry_no' => $registryNo, 'first_name' => $first, 'last_name' => $last, 'gender' => $gender, 'wage' => $wage,
                'upper_unit_id' => $definition(DefinitionType::UpperUnit, 'Genel Müdürlük'),
                'title_id' => $definition(DefinitionType::Title, $title),
                'position_id' => $definition(DefinitionType::Position, $position),
                'status' => $registryNo === '1005' ? Employee::PASSIVE : Employee::ACTIVE,
                ...($registryNo === '1004' ? ['bank_name' => '', 'bank_branch' => ''] : []),
            ]);
        }
    }
}
