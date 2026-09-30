<?php

namespace Tests\Feature\Panel;

use App\Actions\Access\GrantAccess;
use App\Enums\ImportType;
use App\Enums\Permission;
use App\Imports\ExcelTemplateBuilder;
use App\Models\Company;
use App\Models\Firm;
use App\Models\Sector;
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

class CompaniesPageTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->firm = Firm::factory()->create(['name' => 'Acme']);
        $this->client = User::factory()->create();
        app(GrantAccess::class)->handle($this->client, $this->firm, Permission::firmOwnerDefaults());
        $this->actingAs($this->client);
    }

    /**
     * @return array<string, string>
     */
    private function form(array $overrides = []): array
    {
        return [
            'company_no' => '1001',
            'title' => 'Örnek Teknoloji A.Ş.',
            'short_name' => 'Örnek',
            'company_type' => 'anonim',
            'sector_id' => (string) Sector::where('name', 'Bilgi Teknolojileri')->value('id'),
            'tax_number' => TurkishIdentifiers::makeVkn('123456789'),
            'tax_office' => 'Kadıköy',
            'website' => '', 'kep_address' => '', 'trade_registry_no' => '', 'mersis_no' => '', 'phone' => '', 'address' => '',
            ...$overrides,
        ];
    }

    public function test_pages_render(): void
    {
        $company = Company::factory()->for($this->firm)->create(['title' => 'Görünen A.Ş.']);

        $this->get(route('companies.index'))->assertOk()->assertSee('Görünen A.Ş.')->assertSee('İşyeri yok');
        $this->get(route('companies.create'))->assertOk();
        $this->get(route('companies.show', $company))->assertOk()->assertSee('en az bir işyeri');
        $this->get(route('companies.edit', $company))->assertOk();
        $this->get(route('imports.create', 'sirket'))->assertOk()->assertSee('Excel Şablonunu İndir');
    }

    public function test_create_and_edit_company(): void
    {
        Livewire::test('pages::panel.companies.form')
            ->set('form', $this->form(['tax_number' => '123']))
            ->call('save')
            ->assertHasErrors('form.tax_number')
            ->set('form', $this->form())
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $company = Company::where('company_no', '1001')->firstOrFail();
        $this->assertSame($this->firm->id, $company->firm_id);
        $this->assertSame($this->client->id, $company->created_by);

        Livewire::test('pages::panel.companies.form', ['company' => $company])
            ->assertSet('form.title', 'Örnek Teknoloji A.Ş.')
            ->set('form.short_name', 'Örnek Tek')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Örnek Tek', $company->refresh()->short_name);
    }

    public function test_duplicate_company_number_is_shown_on_the_field(): void
    {
        Company::factory()->create(['company_no' => '1001']);

        Livewire::test('pages::panel.companies.form')
            ->set('form', $this->form())
            ->call('save')
            ->assertHasErrors('form.company_no');
    }

    public function test_company_with_workplaces_cannot_be_deleted(): void
    {
        $withWorkplace = Workplace::factory()->create(['company_id' => Company::factory()->for($this->firm)])->company;
        $empty = Company::factory()->for($this->firm)->create();

        Livewire::test('pages::panel.companies.show', ['company' => $withWorkplace])->call('delete');
        Livewire::test('pages::panel.companies.show', ['company' => $empty])->call('delete')->assertRedirect(route('companies.index'));

        $this->assertNotSoftDeleted($withWorkplace);
        $this->assertSoftDeleted($empty);
    }

    public function test_users_without_permission_cannot_create_or_see_other_firms(): void
    {
        $viewer = User::factory()->create();
        app(GrantAccess::class)->handle($viewer, $this->firm, [Permission::CompanyView]);
        $otherCompany = Company::factory()->create();

        $this->actingAs($viewer);

        $this->get(route('companies.create'))->assertForbidden();
        $this->get(route('companies.show', $otherCompany))->assertForbidden();
        $this->get(route('imports.create', 'sirket'))->assertForbidden();
    }

    public function test_pending_firm_cannot_add_companies(): void
    {
        $this->firm->update(['status' => 'pending']);

        $this->get(route('companies.index'))->assertOk()->assertSee('Firma aktif değil');
        $this->get(route('companies.create'))->assertForbidden();
    }

    public function test_opening_another_visible_firms_company_switches_the_panel(): void
    {
        // HRD staff work across firms; client users are bound to their own firm.
        $specialist = User::factory()->payrollSpecialist()->create();
        $other = Firm::factory()->create();
        app(GrantAccess::class)->handle($specialist, $this->firm, [Permission::CompanyView]);
        app(GrantAccess::class)->handle($specialist, $other, [Permission::CompanyView]);
        $company = Company::factory()->for($other)->create();
        $specialist->switchFirm($this->firm);

        $this->actingAs($specialist)->get(route('companies.show', $company))->assertOk();

        $this->assertSame($other->id, $specialist->refresh()->current_firm_id);
    }

    public function test_client_user_cannot_reach_a_firm_they_do_not_belong_to(): void
    {
        $company = Company::factory()->create();

        $this->get(route('companies.show', $company))->assertForbidden();
    }

    public function test_template_download_and_excel_import_flow(): void
    {
        $this->get(route('imports.template', 'sirket'))
            ->assertOk()
            ->assertDownload(ExcelTemplateBuilder::filename(ImportType::Company));

        $good = $this->spreadsheet([
            ['1001', 'Bir A.Ş.', 'Bir', 'Anonim Şirket', 'Bilgi Teknolojileri', TurkishIdentifiers::makeVkn('000000001'), 'Kadıköy'],
            ['1002', 'İki Ltd.', 'İki', 'Limited Şirket', 'İnşaat', TurkishIdentifiers::makeVkn('000000002'), 'Şişli'],
        ]);
        $bad = $this->spreadsheet([
            ['1001', 'Bir A.Ş.', 'Bir', 'Anonim Şirket', 'Bilgi Teknolojileri', '1234567891', 'Kadıköy'],
        ]);

        $component = Livewire::test('pages::panel.imports.upload', ['type' => 'sirket'])
            ->set('file', $bad)
            ->call('upload')
            ->assertSee('1 satırda hata var')
            ->assertDontSee('Onayla ve Oluştur')
            ->call('startOver')
            ->set('file', $good)
            ->call('upload')
            ->assertSee('Tüm satırlar geçerli');

        $this->assertSame(0, Company::count());

        $component->call('confirm')->assertRedirect(route('companies.index'));

        $this->assertSame(['1001', '1002'], $this->firm->companies()->orderBy('company_no')->pluck('company_no')->all());
    }

    public function test_upload_requires_a_spreadsheet(): void
    {
        Livewire::test('pages::panel.imports.upload', ['type' => 'sirket'])
            ->set('file', UploadedFile::fake()->create('notlar.pdf', 10, 'application/pdf'))
            ->call('upload')
            ->assertHasErrors('file');
    }

    /**
     * @param  list<list<string>>  $rows  values for the 7 required company columns, in template order
     */
    private function spreadsheet(array $rows): UploadedFile
    {
        $path = app(ExcelTemplateBuilder::class)->build(ImportType::Company);
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);

        foreach ($rows as $r => $values) {
            foreach ($values as $c => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($c + 1).($r + 2), $value, DataType::TYPE_STRING);
            }
        }

        (new Xlsx($spreadsheet))->save($path);

        return UploadedFile::fake()->createWithContent('sirketler.xlsx', (string) file_get_contents($path));
    }
}
