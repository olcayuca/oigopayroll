<?php

namespace Tests\Feature\Panel;

use App\Actions\Access\GrantAccess;
use App\Enums\ImportType;
use App\Enums\Permission;
use App\Imports\ExcelTemplateBuilder;
use App\Models\Company;
use App\Models\CredentialAccessLog;
use App\Models\District;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use App\Support\TurkishIdentifiers;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class WorkplacesPageTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private Company $company;

    private User $client;

    private District $kadikoy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->kadikoy = District::create(['province_id' => 34, 'name' => 'Kadıköy']);
        $this->firm = Firm::factory()->create();
        $this->company = Company::factory()->for($this->firm)->create(['company_no' => '1001']);
        $this->client = User::factory()->create();
        app(GrantAccess::class)->handle($this->client, $this->firm, Permission::firmOwnerDefaults());
        $this->actingAs($this->client);
    }

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_merge(array_fill_keys(Livewire::new('pages::panel.workplaces.form')::FIELDS, ''), [
            'workplace_no' => '1',
            'branch_name' => 'Merkez',
            'workplace_type' => 'merkez',
            'workplace_kind' => 'normal',
            'title' => 'Örnek A.Ş.',
            'tax_number' => TurkishIdentifiers::makeVkn('123456789'),
            'tax_office' => 'Kadıköy',
            'hazard_class' => 'az_tehlikeli',
            'province_id' => '34',
            'district_id' => (string) $this->kadikoy->id,
            'address' => 'Moda Cad. No:1',
            'sgk_officer_name' => 'Ayşe Yılmaz',
            'sgk_workplace_code' => '000123',
            'ebildirge_officer_name' => 'Ayşe Yılmaz',
            'opening_date' => '2021-03-01',
            'sgk_declaration_username' => '10000000146',
            'sgk_workplace_password' => 'isyeri-sifre',
            'sgk_system_password' => 'sistem-sifre',
            // remaining columns of the customer setup file
            'nace_code' => '62.01.01',
            'labor_sector_id' => '20',
            'sgk_registry_no' => str_repeat('3', 26),
            'sgk_directorate' => 'Kadıköy SGM',
            'sgk_username' => '10000000146',
            'iskur_user_name' => 'Ayşe Yılmaz',
            'iskur_user_code' => '10000000146',
            'iskur_password' => 'iskur-sifre',
            'iskur_registry_no' => '34-1029384',
            'tax_office_user_code' => '1234567890',
            'dvd_username' => 'ornekas',
            'dvd_password' => 'dvd-sifre',
            'dvd_passphrase' => 'dvd-parola',
            'ebeyanname_password' => 'ebeyan-sifre',
            'police_email' => 'bildirim@ornek.com.tr',
            'police_password' => 'emniyet-sifre',
            'bes_company_name' => 'Anadolu Hayat Emeklilik',
            'bes_username' => 'ornek.bes',
            'bes_password' => 'bes-sifre',
            'has_union' => false,
        ], $overrides);
    }

    public function test_pages_render(): void
    {
        $workplace = Workplace::factory()->for($this->company)->create(['branch_name' => 'Kadıköy Şube']);

        $this->get(route('workplaces.index'))->assertOk()->assertSee('Kadıköy Şube');
        $this->get(route('workplaces.create', ['sirket' => $this->company->id]))->assertOk();
        $this->get(route('workplaces.show', $workplace))->assertOk()->assertSee('Genel Bilgiler')->assertSee('Emniyet &amp; BES', false);
        $this->get(route('workplaces.show', [$workplace, 'sekme' => 'sgk']))->assertOk()->assertSee('••••••••');
        $this->get(route('workplaces.edit', $workplace))->assertOk()->assertDontSee($workplace->sgk_system_password);
        $this->get(route('imports.create', 'isyeri'))->assertOk();
    }

    public function test_create_workplace_with_listed_district(): void
    {
        Livewire::test('pages::panel.workplaces.form', ['companyId' => (string) $this->company->id])
            ->set('form', $this->form(['district_id' => '']))
            ->call('save')
            ->assertHasErrors('form.district_name')
            ->set('form.district_id', (string) $this->kadikoy->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $workplace = Workplace::firstOrFail();
        $this->assertSame($this->kadikoy->id, $workplace->district_id);
        $this->assertSame('isyeri-sifre', $workplace->sgk_workplace_password);
        $this->assertSame(0, Company::withoutWorkplaces()->count());
    }

    public function test_manual_location_and_union_fields(): void
    {
        Livewire::test('pages::panel.workplaces.form', ['companyId' => (string) $this->company->id])
            ->set('manualLocation', true)
            ->set('form', $this->form(['province_name' => 'İstanbul', 'district_name' => 'Yeni İlçe', 'has_union' => true]))
            ->call('save')
            ->assertHasErrors('form.union_name')
            ->set('form.union_name', 'Birleşik Metal-İş')
            ->call('save')
            ->assertHasNoErrors();

        $workplace = Workplace::firstOrFail();
        $this->assertSame(34, $workplace->province_id, 'A typed province that matches the list is linked.');
        $this->assertSame('Yeni İlçe', $workplace->district_name);
        $this->assertTrue($workplace->has_union);
    }

    public function test_copy_from_company(): void
    {
        Livewire::test('pages::panel.workplaces.form', ['companyId' => (string) $this->company->id])
            ->call('copyFromCompany')
            ->assertSet('form.title', $this->company->title)
            ->assertSet('form.tax_number', $this->company->tax_number);
    }

    public function test_edit_keeps_credentials_when_left_blank(): void
    {
        $workplace = Workplace::factory()->for($this->company)->create(['sgk_system_password' => 'eski-sifre']);

        Livewire::test('pages::panel.workplaces.form', ['workplace' => $workplace])
            ->assertSet('form.sgk_system_password', '')
            ->set('form.branch_name', 'Yeni Ad')
            ->set('form.district_name', 'Kadıköy')
            ->call('save')
            ->assertHasNoErrors();

        $workplace->refresh();
        $this->assertSame('Yeni Ad', $workplace->branch_name);
        $this->assertSame('eski-sifre', $workplace->sgk_system_password);
    }

    public function test_reveal_credential_requires_permission_and_is_logged(): void
    {
        $workplace = Workplace::factory()->for($this->company)->create(['sgk_system_password' => 'gizli']);

        Livewire::test('pages::panel.workplaces.show', ['workplace' => $workplace])
            ->set('tab', 'sgk')
            ->call('reveal', 'sgk_system_password')
            ->assertSee('gizli');

        $this->assertSame(1, CredentialAccessLog::count());

        $viewer = User::factory()->create();
        app(GrantAccess::class)->handle($viewer, $this->firm, [Permission::WorkplaceView]);
        $this->actingAs($viewer);

        Livewire::test('pages::panel.workplaces.show', ['workplace' => $workplace])
            ->set('tab', 'sgk')
            ->assertDontSee('Göster')
            ->call('reveal', 'sgk_system_password')
            ->assertForbidden();

        $this->assertSame(1, CredentialAccessLog::count());
    }

    public function test_workplace_excel_import(): void
    {
        $file = $this->workplaceSheet([
            ['Şirket Numarası' => '1001', 'İşyeri Numarası *' => '1', 'İşyeri Şube Adı *' => 'Merkez', 'İşyeri Tipi *' => 'Merkez İşyeri',
                'İşyeri Türü *' => 'Normal', 'Ünvan' => 'Örnek A.Ş.', 'Vergi Numarası *' => TurkishIdentifiers::makeVkn('012345678'),
                'Vergi Dairesi *' => 'Kadıköy', 'Tehlike Sınıfı *' => 'Tehlikeli', 'İl *' => 'İstanbul', 'İlçe *' => 'Kadıköy',
                'İşyeri Açık Adresi *' => 'Moda Cad. 1', 'SGK İşyeri Yetkilisi Adı Soyadı *' => 'Ayşe', 'SGK İşyeri Kodu *' => '1',
                'e-Bildirge Yetkilisi Adı Soyadı *' => 'Ayşe', 'İşyeri Açılış Tarihi' => '01.03.2021',
                'SGK Bildirge Kullanıcı Adı (TCKN) *' => '10000000146', 'SGK İşyeri Şifresi *' => 'a', 'SGK Sistem Şifresi *' => 'b',
                'ÇSGB İşkolu *' => '20', 'NACE Kodu *' => '62.01.01', 'İşyeri SGK Sicil Numarası *' => str_repeat('4', 26),
                'Bağlı Bulunulan SGK Müdürlüğü *' => 'Kadıköy SGM', 'SGK Kullanıcı Adı (TCKN) *' => '10000000146',
                'İŞKUR Kullanıcı Adı Soyadı *' => 'Ayşe', 'İŞKUR Kullanıcı Kodu (TCKN) *' => '10000000146', 'İŞKUR Şifresi *' => 'c',
                'İŞKUR Sicil Numarası *' => '34-1', 'Vergi Dairesi Kullanıcı Kodu *' => '1', 'Dijital Vergi Dairesi Kullanıcı Adı *' => 'd',
                'Dijital Vergi Dairesi Şifre *' => 'e', 'Dijital Vergi Dairesi Parola *' => 'f', 'e-Beyanname Şifresi *' => 'g',
                'Emniyet (Karakol) Bildirimi E-posta *' => 'b@ornek.com', 'Emniyet (Karakol) Bildirimi Şifre *' => 'h',
                'BES Firma Adı *' => 'BES A.Ş.', 'BES Firma Kullanıcı Adı *' => 'i', 'BES Firma Şifre *' => 'j'],
        ]);

        Livewire::test('pages::panel.imports.upload', ['type' => 'isyeri'])
            ->set('file', $file)
            ->call('upload')
            ->assertSee('Tüm satırlar geçerli')
            ->call('confirm')
            ->assertRedirect(route('workplaces.index'));

        $workplace = Workplace::firstOrFail();
        $this->assertSame($this->kadikoy->id, $workplace->district_id);
        $this->assertSame($this->company->id, $workplace->company_id);
    }

    public function test_cannot_add_workplace_to_company_of_inactive_firm(): void
    {
        $this->firm->update(['status' => 'passive']);

        $this->get(route('workplaces.create'))->assertForbidden();
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function workplaceSheet(array $rows): UploadedFile
    {
        $path = app(ExcelTemplateBuilder::class)->build(ImportType::Workplace);
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);
        $headers = $sheet->rangeToArray('A1:BZ1')[0];

        foreach ($rows as $r => $row) {
            foreach ($row as $header => $value) {
                $column = Coordinate::stringFromColumnIndex((int) array_search($header, $headers, true) + 1);
                $sheet->setCellValueExplicit($column.($r + 2), $value, DataType::TYPE_STRING);
            }
        }

        (new Xlsx($spreadsheet))->save($path);

        return UploadedFile::fake()->createWithContent('isyerleri.xlsx', (string) file_get_contents($path));
    }
}
