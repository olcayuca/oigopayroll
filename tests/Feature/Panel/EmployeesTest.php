<?php

namespace Tests\Feature\Panel;

use App\Actions\Access\GrantAccess;
use App\Actions\Employees\SaveEmployee;
use App\Enums\CodeList;
use App\Enums\DefinitionType;
use App\Enums\ImportType;
use App\Enums\Permission;
use App\Enums\ScopeType;
use App\Imports\ImportService;
use App\Models\Company;
use App\Models\Definition;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\PayrollCode;
use App\Models\User;
use App\Models\Workplace;
use App\Rules\Iban;
use App\Support\TurkishIdentifiers;
use App\Validation\EmployeeInput;
use Database\Seeders\PayrollCodeSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Personel module: the "Personel Bilgileri" sheet of the customer setup file (docs/KURULUM_DOSYASI.md §2).
 */
class EmployeesTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private Company $company;

    private Workplace $workplace;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferenceDataSeeder::class, PayrollCodeSeeder::class]);
        $this->firm = Firm::factory()->create(['name' => 'Oigo Grup']);
        $this->company = Company::factory()->for($this->firm)->create(['company_no' => '101', 'title' => 'Oigo Yazılım A.Ş.', 'short_name' => 'Oigo Yazılım']);
        $this->workplace = Workplace::factory()->for($this->company)->create(['branch_name' => 'Merkez Ofis', 'workplace_no' => '001']);
        $this->owner = User::factory()->create();
        app(GrantAccess::class)->handle($this->owner, $this->firm, Permission::firmOwnerDefaults());
        $this->actingAs($this->owner);
    }

    /**
     * Form input of a complete record (Turkish formats on purpose).
     *
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'company_id' => $this->company->id, 'workplace_id' => $this->workplace->id,
            'registry_no' => '1001', 'tckn' => '10000000146', 'first_name' => 'Ayşe', 'last_name' => 'Yıldız',
            'personal_email' => 'ayse@example.com', 'mobile_phone' => '+90 532 000 00 00',
            'birth_date' => '1990-04-12', 'gender' => 'KADIN', 'hire_date' => '2022-03-01', 'seniority_date' => '2022-03-01', 'leave_base_date' => '2022-03-01',
            'upper_unit_id' => EmployeeInput::NEW_PREFIX.'Genel Müdürlük', 'title_id' => EmployeeInput::NEW_PREFIX.'Müdür',
            'position_id' => EmployeeInput::NEW_PREFIX.'İnsan Kaynakları Müdürü', 'leave_manager_registry_no' => '1001',
            'occupation_code' => '2423.08', 'insurance_branch' => 'Tüm Sigorta Kolları (Zorunlu)', 'sgk_status' => 'Normal',
            'employment_type' => 'Belirsiz Süreli', 'duty_code' => 'İşçi', 'sgk_document_type' => '1',
            'bank_name' => 'Garanti BBVA', 'bank_branch' => 'Ataşehir', 'iban' => 'TR76 0006 2001 2340 0006 2987 65', 'account_no' => '6298765',
            'wage_period' => 'Aylık', 'currency' => 'TRY', 'wage_type' => 'Brüt', 'wage' => '82.400,00',
            'is_minimum_wage' => 'Hayır', 'minimum_wage_exemption' => 'Evet', 'bes_rate' => '%3', 'cumulative_tax_base' => '0,00',
            'tax_exemption_start_month' => 'Ocak', 'previous_sgk_base_1' => '0', 'previous_sgk_base_2' => '0',
            'work_model' => 'Hibrit', 'contract_type' => 'Tam Zamanlı', 'is_shift_worker' => false,
            'shift_start' => '9:00', 'shift_end' => '18:00', 'weekly_rest' => 'Cumartesi & Pazar', 'remaining_leave_days' => '14',
        ], $overrides);
    }

    public function test_pages_render(): void
    {
        $employee = Employee::factory()->for($this->workplace)->create(['first_name' => 'Mehmet', 'last_name' => 'Kaya']);

        $this->get(route('employees.index'))->assertOk()->assertSee('Mehmet Kaya')->assertSee('Excel ile Aktar');
        $this->get(route('employees.create'))->assertOk()->assertSee('Kimlik &amp; İletişim', false)->assertSee('role="tablist"', false);
        $this->get(route('employees.show', $employee))->assertOk()->assertSee('Mehmet Kaya')->assertDontSee($employee->tckn);
        $this->get(route('employees.edit', $employee))->assertOk()->assertDontSee($employee->tckn)->assertDontSee((string) $employee->iban);
        $this->get(route('definitions.index'))->assertOk()->assertSee('Üst Birimler');
        $this->get(route('imports.create', 'personel'))->assertOk();
        $this->get(route('imports.template', 'personel'))->assertOk();

        foreach (range(1, 5) as $step) {
            $this->get(route('setup.wizard', ['adim' => $step]))->assertOk()->assertSee('KURULUM SİHİRBAZI');
        }
        Livewire::test('pages::panel.setup.wizard')
            ->assertSet('step', 1)
            ->call('go', 4)
            ->assertSee('Personel Bilgileri')
            ->assertSee('1 personel');
    }

    public function test_create_normalises_turkish_formats_encrypts_and_creates_new_definitions(): void
    {
        $employee = app(SaveEmployee::class)->create($this->firm, $this->input(), $this->owner);

        $this->assertSame('82400.00', $employee->wage);
        $this->assertSame('3.00', $employee->bes_rate);
        $this->assertSame('Kadın', $employee->gender);
        $this->assertSame('01', $employee->sgk_document_type);
        $this->assertSame(1, $employee->tax_exemption_start_month);
        $this->assertFalse($employee->is_minimum_wage);
        $this->assertTrue($employee->minimum_wage_exemption);
        $this->assertSame('09:00', substr((string) $employee->shift_start, 0, 5));
        $this->assertSame('TR760006200123400006298765', $employee->iban);
        $this->assertSame(100, $employee->completionPercent());

        $raw = (array) DB::table('employees')->where('id', $employee->id)->first();
        $this->assertStringNotContainsString('10000000146', implode('|', array_map('strval', $raw)), 'TCKN is encrypted at rest.');
        $this->assertStringNotContainsString('TR760006200123400006298765', implode('|', array_map('strval', $raw)));

        $position = Definition::where('type', DefinitionType::Position)->sole();
        $this->assertSame('İnsan Kaynakları Müdürü', $position->name);
        $this->assertSame('Müdür', $employee->title?->name);
        $this->assertSame('Genel Müdürlük', $employee->upperUnit?->name);

        // TCKN and sicil are unique in the firm.
        try {
            app(SaveEmployee::class)->create($this->firm, $this->input(['registry_no' => '1002']), $this->owner);
            $this->fail('Expected validation to fail.');
        } catch (ValidationException $e) {
            $this->assertSame(['tckn'], array_keys($e->errors()));
        }
    }

    public function test_required_fields_and_formats_are_enforced(): void
    {
        try {
            app(SaveEmployee::class)->create($this->firm, $this->input([
                'tckn' => '12345678901', 'iban' => 'TR00 1234', 'occupation_code' => '24', 'gender' => 'Belirsiz',
                'sgk_document_type' => '99', 'personal_email' => null, 'upper_unit_id' => null, 'shift_start' => '25:00',
            ]), $this->owner);
            $this->fail('Expected validation to fail.');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(
                ['tckn', 'iban', 'occupation_code', 'gender', 'sgk_document_type', 'personal_email', 'upper_unit_id', 'shift_start'],
                array_keys($e->errors()),
            );
        }

        $this->assertSame(0, Definition::count(), 'Nothing is created when the record is invalid.');
    }

    public function test_occupation_code_is_checked_against_the_list_once_it_is_loaded(): void
    {
        PayrollCode::create(['list' => CodeList::Occupations, 'code' => '2411.01', 'name' => 'Muhasebeci']);

        try {
            app(SaveEmployee::class)->create($this->firm, $this->input(['occupation_code' => '2423.08']), $this->owner);
            $this->fail('A code missing from the list is rejected.');
        } catch (ValidationException $e) {
            $this->assertSame(['occupation_code'], array_keys($e->errors()));
        }

        $this->assertSame('2411.01', app(SaveEmployee::class)->create($this->firm, $this->input(['occupation_code' => '2411.01']), $this->owner)->occupation_code);
    }

    public function test_update_keeps_encrypted_fields_when_left_blank(): void
    {
        $employee = app(SaveEmployee::class)->create($this->firm, $this->input(), $this->owner);

        Livewire::test('pages::panel.employees.form', ['employee' => $employee])
            ->assertSet('form.tckn', '')
            ->assertSet('form.iban', '')
            ->assertSet('form.wage', '82.400,00')
            ->set('form.last_name', 'Demir')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('employees.show', $employee));

        $employee->refresh();
        $this->assertSame('Demir', $employee->last_name);
        $this->assertSame('10000000146', $employee->tckn);
        $this->assertSame('TR760006200123400006298765', $employee->iban);
    }

    public function test_form_errors_open_the_first_failing_tab(): void
    {
        Livewire::test('pages::panel.employees.form')
            ->set('form.registry_no', '2001')
            ->set('tab', 'calisma')
            ->call('save')
            ->assertHasErrors('form.tckn')
            ->assertSet('tab', 'kimlik');
    }

    public function test_definitions_are_managed_and_used_ones_cannot_be_deleted(): void
    {
        Livewire::test('pages::panel.definitions.index')
            ->call('create')
            ->set('form.code', 'gm')
            ->set('form.name', 'Genel Müdürlük')
            ->call('save')
            ->assertHasNoErrors();

        $upper = Definition::where('type', DefinitionType::UpperUnit)->sole();
        $this->assertSame('GM', $upper->code);

        Livewire::test('pages::panel.definitions.index', ['typeSlug' => 'birim'])
            ->call('create')
            ->set('form.code', 'IK')
            ->set('form.name', 'İnsan Kaynakları')
            ->set('form.parent_id', (string) $upper->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('İnsan Kaynakları');

        app(SaveEmployee::class)->create($this->firm, $this->input(['upper_unit_id' => $upper->id]), $this->owner);

        Livewire::test('pages::panel.definitions.index')
            ->assertSee('1 personel')
            ->call('delete', $upper->id);

        $this->assertModelExists($upper);
    }

    public function test_personnel_visibility_follows_workplace_grants(): void
    {
        $other = Workplace::factory()->for($this->company)->create(['branch_name' => 'Ankara']);
        $mine = Employee::factory()->for($this->workplace)->create(['first_name' => 'Görünen']);
        $hidden = Employee::factory()->for($other)->create(['first_name' => 'Gizli']);

        $clerk = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($clerk, $this->workplace, [Permission::WorkplaceView, Permission::EmployeeView]);
        $this->actingAs($clerk);

        $this->get(route('employees.index'))->assertOk()->assertSee('Görünen')->assertDontSee('Gizli');
        $this->get(route('employees.show', $mine))->assertOk();
        $this->get(route('employees.show', $hidden))->assertForbidden();
        $this->get(route('employees.edit', $mine))->assertForbidden();
        $this->get(route('employees.create'))->assertForbidden();

        $noPersonnel = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($noPersonnel, $this->workplace, [Permission::WorkplaceView]);
        $this->actingAs($noPersonnel);
        $this->get(route('employees.index'))->assertOk()->assertDontSee('Görünen');
    }

    public function test_existing_grants_received_employee_permissions(): void
    {
        $this->assertTrue($this->owner->hasPermissionOn(Permission::EmployeeCreate, $this->workplace));
        $this->assertSame(ScopeType::Firm, $this->owner->accessGrants()->first()?->scope_type);
    }

    /**
     * The customer's setup workbook: "Firma Bilgileri" first, "Personel Bilgileri" second, with its headers.
     */
    public function test_customer_setup_file_personnel_sheet_imports_and_updates(): void
    {
        $headers = ['Sicil No', 'TC Kimlik No', 'Adı', 'Soyadı', 'İkinci Soyadı', 'Şirket E-posta', 'Kişisel E-posta', 'Cep Telefonu',
            'İş Telefonu', 'Oturulan Adres', 'İl', 'İlçe', 'Doğum Tarihi', 'İşe Giriş Tarihi', 'Kıdeme Esas Tarihi', 'İzne Esas Tarihi',
            'Yaka', 'Cinsiyet', 'Medeni Hal', 'Eğitim Durumu', 'Mezuniyet Bölümü', 'Askerlik', 'Firma', 'SGK Firma', 'İş yeri Şube Adı',
            'İş Ailesi', 'Birim', 'Üst Birim', 'Görev Tipi', 'SGK Meslek Kodu', 'Sigorta Kolu', 'SGK Statü', 'Çalışan Tipi', 'Görev Kodu',
            'SGK Belge Türü', 'Banka Adı', 'Şube Adı', 'Iban Numarası', 'Hesap No', 'Otomatik Bes Oran', 'Kümülatif Gelir Vergisi Mat. ',
            'Vergi İstisnası Başlangıç Ayı', 'Bir Önceki Dönem Devreden SGK Matrahı', 'İki Önceki Dönem Devreden SGK Matrahı',
            'Ücret Periyodu', 'Para Birimi', 'Ücret Tipi', 'Ücret', 'Asgari Ücretli', 'Asgari Ücret Vergi İstisnasına Tabi Mi?',
            'Ar-Ge İndirim Oranı', 'Engellilik Derecesi', 'Engelli Gelir Verg. İndiriminden Faydalanıyor mu? ', 'Engellilik Bitiş Tarihi',
            'Masraf Grubu', 'Masraf Grubu Oranı', 'Çalışma Modeli', 'Sözleşme Türü', 'Unvan', 'Pozisyon', 'Seviye', 'İzin Yönetici Sicil No',
            'Fonksiyonel Yönetici Sicil No', 'Kalan Yıllık İzin Hakkı', 'Vardiyalı mı Çalışıyor?', 'Mesai Başlangıç Saati',
            'Mesai Bitiş Saati', 'Hafta Tatili Gün(leri)'];
        $row = fn (string $sicil, string $tckn, string $iban) => [$sicil, $tckn, 'Elif', 'Demir', '', '', 'elif@example.com', '05320000000',
            '', '', 'İstanbul', 'Ataşehir', '12.04.1990', '01.03.2022', '01.03.2022', '01.03.2022',
            'Beyaz Yaka', 'Kadın', 'Evli', 'Lisans', 'İşletme', '', 'Oigo Yazılım A.Ş.', 'Oigo Yazılım A.Ş.', 'Merkez Ofis',
            'Yönetim', 'Eğitim Birimi', 'İnsan Kaynakları Müdürlüğü', 'Kadrolu', '2411.12', 'Tüm Sigorta Kolları (Zorunlu)', 'Normal',
            'Belirsiz Süreli', 'İşçi', '1', 'Garanti BBVA', 'Ataşehir', $iban, '6298765', '3%', '0',
            'Ocak', '0', '0', 'Aylık', 'TRY', 'Net', '54.800,00', 'Hayır', 'Evet',
            '0%', '', '', '', 'Genel Yönetim', '%100', 'Hibrit', 'Tam Zamanlı', 'Uzman', 'Muhasebe Uzmanı', '', '1001',
            'İzin onayı yoktur, ancak görüntüleyebilir.', '14', 'Hayır', '09:00', '18:00', 'Cumartesi & Pazar'];

        $book = function (array $rows) use ($headers): string {
            $spreadsheet = new Spreadsheet;
            $spreadsheet->getActiveSheet()->setTitle('Firma Bilgileri')->fromArray([['İş Yeri Tipi', 'Şirket Adı', 'İş Yeri Şube Adı']]);
            $sheet = $spreadsheet->createSheet()->setTitle('Personel Bilgileri');
            $sheet->fromArray([$headers]);
            foreach ($rows as $r => $values) {
                foreach ($values as $c => $value) {
                    $sheet->setCellValueExplicit([$c + 1, $r + 2], $value, DataType::TYPE_STRING);
                }
            }
            $sheet->setCellValue('C63', 'Kırmızı alanlar zorunludur.');
            $path = tempnam(sys_get_temp_dir(), 'per').'.xlsx';
            (new Xlsx($spreadsheet))->save($path);

            return $path;
        };

        $iban = Iban::make('0006200123400006298765');
        $listRow = array_fill(0, count($headers), '');
        $listRow[19] = 'Doktora';   // the sheet keeps its dropdown values in the first rows
        $listRow[30] = 'Çırak';

        $import = app(ImportService::class)->preview(ImportType::Employee, $this->firm, $book([
            $listRow,
            $row('2001', TurkishIdentifiers::makeTckn('100000001'), $iban),
            $row('2002', TurkishIdentifiers::makeTckn('100000002'), $iban),
        ]), 'KURULUM DOSYASI.xlsx', $this->owner);

        $this->assertSame([], $import->file_errors ?? []);
        $this->assertSame(2, $import->total_rows);
        $this->assertSame(0, $import->error_rows, json_encode($import->rows->pluck('errors'), JSON_UNESCAPED_UNICODE) ?: '');

        app(ImportService::class)->confirm($import, $this->owner);

        $employee = Employee::where('registry_no', '2001')->sole();
        $this->assertSame($this->workplace->id, $employee->workplace_id);
        $this->assertSame('54800.00', $employee->wage);
        $this->assertSame('0', $employee->rnd_rate);
        $this->assertSame('Muhasebe Uzmanı', $employee->position?->name);
        $this->assertSame('İnsan Kaynakları Müdürlüğü', $employee->unit?->parent?->name, 'A new unit hangs under the new upper unit.');
        $this->assertSame(1, Definition::where('type', DefinitionType::Position)->count(), 'Definitions are created once.');
        $this->assertSame(100, $employee->completionPercent());

        // Re-upload: matched by sicil and updated; blank encrypted cells (IBAN) keep the stored value.
        $changed = $row('2001', '', '');
        $changed[47] = '60.000,00'; // Ücret
        $update = app(ImportService::class)->preview(ImportType::Employee, $this->firm, $book([$changed]), 'guncelle.xlsx', $this->owner);
        $this->assertSame('update', $update->rows->first()?->action);
        $this->assertSame(0, $update->error_rows, json_encode($update->rows->pluck('errors'), JSON_UNESCAPED_UNICODE) ?: '');
        app(ImportService::class)->confirm($update, $this->owner);

        $employee->refresh();
        $this->assertSame('60000.00', $employee->wage);
        $this->assertSame($iban, $employee->iban);
        $this->assertSame('Elif', $employee->first_name);
    }

    public function test_import_reports_unknown_workplace(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([['Sicil No', 'Firma', 'SGK Firma', 'İş yeri Şube Adı'], ['3001', 'Oigo Yazılım A.Ş.', '', 'Olmayan Şube']]);
        $path = tempnam(sys_get_temp_dir(), 'bad').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $import = app(ImportService::class)->preview(ImportType::Employee, $this->firm, $path, 'eksik.xlsx', $this->owner);

        $this->assertNotEmpty($import->file_errors, 'Required columns are missing.');
    }
}
