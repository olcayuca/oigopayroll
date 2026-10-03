<?php

namespace Tests\Feature\Panel;

use App\Actions\Access\GrantAccess;
use App\Actions\Companies\SaveCompany;
use App\Actions\Employees\SaveEmployee;
use App\Actions\Firms\ManageFirmDocuments;
use App\Actions\Workplaces\SaveWorkplace;
use App\Enums\AuditEvent;
use App\Enums\DocumentType;
use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\FirmDocument;
use App\Models\User;
use App\Models\Workplace;
use App\Support\Audit;
use Database\Seeders\PayrollCodeSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * İşlem Geçmişi: scoped per firm / company (şirket) / workplace (şube), filterable per user, detailed per field.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private Company $company;

    private Company $otherCompany;

    private Workplace $istanbul;

    private Workplace $ankara;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferenceDataSeeder::class, PayrollCodeSeeder::class]);
        $this->firm = Firm::factory()->create();
        $this->company = Company::factory()->for($this->firm)->create(['short_name' => 'Oigo Yazılım']);
        $this->otherCompany = Company::factory()->for($this->firm)->create(['short_name' => 'Oigo Lojistik']);
        $this->istanbul = Workplace::factory()->for($this->company)->create(['branch_name' => 'İstanbul']);
        $this->ankara = Workplace::factory()->for($this->otherCompany)->create(['branch_name' => 'Ankara']);
        $this->owner = User::factory()->create(['name' => 'Selin Korkmaz', 'firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($this->owner, $this->firm, Permission::firmOwnerDefaults());
        $this->actingAs($this->owner);
    }

    public function test_updates_record_scope_and_field_level_changes_without_secrets(): void
    {
        $employee = Employee::factory()->for($this->istanbul)->create(['wage' => 39000, 'first_name' => 'Burak']);

        $current = collect($employee->fresh()?->getAttributes())
            ->except(['id', 'firm_id', 'status', 'tckn_hash', 'created_by', 'created_at', 'updated_at', 'deleted_at', ...Employee::SECRET_FIELDS])->all();
        app(SaveEmployee::class)->update($employee, [...$current, 'wage' => '39.500,00', 'iban' => 'TR33 0006 1005 1978 6457 8413 26']);

        $log = AuditLog::where('event', AuditEvent::EmployeeUpdated)->sole();
        $this->assertSame([$this->firm->id, $this->company->id, $this->istanbul->id], [$log->firm_id, $log->company_id, $log->workplace_id]);

        $changes = collect($log->changes())->keyBy('field');
        $this->assertSame(['label' => 'Ücret', 'old' => '39.000,00', 'new' => '39.500,00'], collect($changes['wage'])->only('label', 'old', 'new')->all());
        $this->assertSame('•••••• (değiştirildi)', $changes['iban']['new']);
        $this->assertStringNotContainsString('TR330006100519786457841326', (string) json_encode($log->properties));

        // Workplace: enum labels and references are readable.
        app(SaveWorkplace::class)->update($this->istanbul, [...$this->istanbul->only(['workplace_no', 'branch_name']), 'hazard_class' => 'cok_tehlikeli']
            + collect($this->istanbul->getAttributes())->except(['id', 'company_id', 'created_at', 'updated_at', 'deleted_at', ...Workplace::SECRET_FIELDS])->all());
        $workplaceLog = AuditLog::where('event', AuditEvent::WorkplaceUpdated)->sole();
        $this->assertSame('Çok Tehlikeli', collect($workplaceLog->changes())->firstWhere('field', 'hazard_class')['new'] ?? null);
    }

    public function test_saving_an_unchanged_record_writes_no_history(): void
    {
        $employee = Employee::factory()->for($this->istanbul)->create(['shift_start' => '09:00:00', 'shift_end' => '18:00:00']);

        Livewire::test('pages::panel.employees.form', ['employee' => $employee])
            ->assertSet('form.shift_start', '09:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, AuditLog::where('event', AuditEvent::EmployeeUpdated)->count(), 'Times "09:00" and "09:00:00" are equal.');
    }

    public function test_page_filters_by_company_workplace_user_and_record(): void
    {
        $colleague = User::factory()->create(['name' => 'Hakan Aydın', 'firm_id' => $this->firm->id]);
        Audit::log(AuditEvent::CompanyUpdated, 'İstanbul şirket işlemi', $this->company, user: $colleague);
        Audit::log(AuditEvent::WorkplaceUpdated, 'İstanbul şube işlemi', $this->istanbul);
        Audit::log(AuditEvent::WorkplaceUpdated, 'Ankara şube işlemi', $this->ankara);
        $employee = Employee::factory()->for($this->istanbul)->create();
        Audit::log(AuditEvent::EmployeeUpdated, 'Personel işlemi', $employee);
        Audit::log(AuditEvent::WorkplaceUpdated, 'Başka firma işlemi', Workplace::factory()->create());

        $this->get(route('audit.index'))->assertOk()
            ->assertSee('İstanbul şube işlemi')->assertSee('Ankara şube işlemi')->assertDontSee('Başka firma işlemi');

        Livewire::test('pages::panel.audit.index')
            ->set('company', (string) $this->company->id)
            ->assertSee('İstanbul şirket işlemi')->assertSee('İstanbul şube işlemi')->assertSee('Personel işlemi')->assertDontSee('Ankara şube işlemi')
            ->set('workplace', (string) $this->istanbul->id)
            ->assertDontSee('İstanbul şirket işlemi')->assertSee('İstanbul şube işlemi')
            ->call('clearFilters')
            ->set('user', (string) $colleague->id)
            ->assertSee('İstanbul şirket işlemi')->assertDontSee('Ankara şube işlemi')
            ->call('clearFilters')
            ->set('record', 'personel:'.$employee->id)
            ->assertSee('Personel işlemi')->assertDontSee('İstanbul şube işlemi');

        $this->get(route('audit.export', ['sube' => $this->ankara->id]))->assertOk();
        $this->assertSame(1, AuditLog::where('event', AuditEvent::DataExported)->where('firm_id', $this->firm->id)->count());
    }

    public function test_row_opens_the_change_details(): void
    {
        app(SaveCompany::class)->update($this->company, [...collect($this->company->getAttributes())
            ->except(['id', 'firm_id', 'created_by', 'created_at', 'updated_at', 'deleted_at'])->all(), 'title' => 'Oigo Yazılım Teknoloji A.Ş.']);
        $log = AuditLog::where('event', AuditEvent::CompanyUpdated)->sole();

        Livewire::test('pages::panel.audit.index')
            ->assertDontSee('Oigo Yazılım Teknoloji A.Ş.</td>', false)
            ->call('toggle', $log->id)
            ->assertSee('DEĞİŞİKLİKLER (1)')
            ->assertSee('Oigo Yazılım Teknoloji A.Ş.');
    }

    public function test_workplace_level_viewer_sees_only_that_workplace(): void
    {
        Audit::log(AuditEvent::WorkplaceUpdated, 'İstanbul şube işlemi', $this->istanbul);
        Audit::log(AuditEvent::WorkplaceUpdated, 'Ankara şube işlemi', $this->ankara);
        Audit::log(AuditEvent::FirmUpdated, 'Firma geneli işlem', $this->firm);

        $branchManager = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($branchManager, $this->istanbul, [Permission::WorkplaceView, Permission::FirmViewAudit]);
        $this->actingAs($branchManager);

        $this->get(route('audit.index'))->assertOk()
            ->assertSee('İstanbul şube işlemi')->assertDontSee('Ankara şube işlemi')->assertDontSee('Firma geneli işlem');

        $noAudit = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($noAudit, $this->istanbul, [Permission::WorkplaceView]);
        $this->actingAs($noAudit);
        $this->get(route('audit.index'))->assertForbidden();
        $this->get(route('audit.export'))->assertForbidden();
    }

    /**
     * Firma (company_id) A, but working at a workplace of company B (SGK firma): both companies see the record.
     */
    public function test_personnel_of_another_companys_workplace_belongs_to_both_companies(): void
    {
        $employee = Employee::factory()->create([
            'workplace_id' => $this->ankara->id, 'company_id' => $this->company->id, 'firm_id' => $this->firm->id,
        ]);
        Audit::log(AuditEvent::EmployeeUpdated, 'Ankara personeli işlemi', $employee);

        Livewire::test('pages::panel.audit.index')
            ->set('company', (string) $this->otherCompany->id)->assertSee('Ankara personeli işlemi')
            ->set('company', (string) $this->company->id)->assertSee('Ankara personeli işlemi');

        $lojistikManager = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($lojistikManager, $this->otherCompany, [Permission::CompanyView, Permission::FirmViewAudit]);
        $this->actingAs($lojistikManager);
        $this->get(route('audit.index'))->assertOk()->assertSee('Ankara personeli işlemi');
    }

    public function test_company_documents_are_placed_under_the_company(): void
    {
        $document = FirmDocument::create([
            'firm_id' => $this->firm->id, 'company_id' => $this->company->id, 'type' => DocumentType::cases()[0],
            'title' => 'Vergi levhası', 'disk' => 'local', 'path' => 'x.pdf', 'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size' => 1,
        ]);
        app(ManageFirmDocuments::class)->delete($document, $this->owner);

        $log = AuditLog::where('event', AuditEvent::DocumentDeleted)->sole();
        $this->assertSame([$this->firm->id, $this->company->id], [$log->firm_id, $log->company_id]);
    }

    public function test_excel_import_rows_are_marked_with_their_file(): void
    {
        Audit::within(['source' => 'excel', 'import_file' => 'KURULUM DOSYASI.xlsx'], fn () => Audit::log(AuditEvent::WorkplaceCreated, 'Aktarılan işyeri', $this->istanbul));
        Audit::log(AuditEvent::WorkplaceUpdated, 'Elle değişiklik', $this->istanbul);

        $this->assertSame('KURULUM DOSYASI.xlsx', AuditLog::where('description', 'Aktarılan işyeri')->sole()->properties['import_file'] ?? null);
        $this->assertNull(AuditLog::where('description', 'Elle değişiklik')->sole()->properties['import_file'] ?? null);
        $this->get(route('audit.index'))->assertSee('Excel: KURULUM DOSYASI.xlsx');
    }

    public function test_record_pages_link_to_their_history(): void
    {
        $employee = Employee::factory()->for($this->istanbul)->create();

        $this->get(route('companies.show', $this->company))->assertSee(route('audit.index', ['sirket' => $this->company->id]), false);
        $this->get(route('workplaces.show', $this->istanbul))->assertSee(route('audit.index', ['sube' => $this->istanbul->id]), false);
        $this->get(route('employees.show', $employee))->assertSee('kayit=personel%3A'.$employee->id, false);
        $this->get(route('dashboard'))->assertSee('İşlem Geçmişi');
    }
}
