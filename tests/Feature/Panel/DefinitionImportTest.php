<?php

namespace Tests\Feature\Panel;

use App\Actions\Access\GrantAccess;
use App\Enums\DefinitionType;
use App\Enums\ImportType;
use App\Enums\Permission;
use App\Imports\ImportService;
use App\Models\Definition;
use App\Models\Firm;
use App\Models\User;
use Database\Seeders\PayrollCodeSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Tanımlar Excel import: all types in one sheet, matched by type + code (or name), parents created when missing.
 */
class DefinitionImportTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferenceDataSeeder::class, PayrollCodeSeeder::class]);
        $this->firm = Firm::factory()->create();
        $this->owner = User::factory()->create();
        app(GrantAccess::class)->handle($this->owner, $this->firm, Permission::firmOwnerDefaults());
        $this->actingAs($this->owner);
    }

    /**
     * @param  list<list<string|null>>  $rows
     */
    private function file(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([['Tür', 'Ad', 'Kod', 'Üst Tanım', 'Durum', 'Masraf Merkezi Kodu'], ...$rows]);
        $path = tempnam(sys_get_temp_dir(), 'tanim').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function find(DefinitionType $type, string $name): ?Definition
    {
        return Definition::query()->ofType($this->firm, $type)->where('name', $name)->first();
    }

    public function test_definitions_import_with_parents_and_reports_errors(): void
    {
        $service = app(ImportService::class);
        $rows = [
            ['Üst Birim', 'Genel Müdürlük', 'GM', null, 'Aktif', null],
            ['Birim', 'İnsan Kaynakları', 'IK', 'Genel Müdürlük', null, null],
            ['Pozisyon', 'İK Uzmanı', null, 'İnsan Kaynakları', null, null],
            ['Birimler', 'Satış', null, 'Ticari Direktörlük', null, null],
            ['Masraf Grubu', 'Genel Yönetim', 'MG01', null, null, '770.01'],
            ['Unvan', 'Uzman', null, 'Genel Müdürlük', null, null],
            ['Departman', 'X', null, null, null, null],
        ];

        $import = $service->preview(ImportType::Definition, $this->firm, $this->file($rows), 'tanimlar.xlsx', $this->owner);
        $this->assertSame([7, 2], [$import->total_rows, $import->error_rows]);
        $this->assertArrayHasKey('parent', $import->rows->firstWhere('row_number', 7)?->errors ?? []);
        $this->assertArrayHasKey('type', $import->rows->firstWhere('row_number', 8)?->errors ?? []);

        $import = $service->preview(ImportType::Definition, $this->firm, $this->file(array_slice($rows, 0, 5)), 'tanimlar.xlsx', $this->owner);
        $this->assertSame(0, $import->error_rows);
        $service->confirm($import, $this->owner);

        $this->assertSame($this->find(DefinitionType::UpperUnit, 'Genel Müdürlük')?->id, $this->find(DefinitionType::Unit, 'İnsan Kaynakları')?->parent_id);
        $this->assertSame($this->find(DefinitionType::Unit, 'İnsan Kaynakları')?->id, $this->find(DefinitionType::Position, 'İK Uzmanı')?->parent_id,
            'A parent from an earlier row of the same file is used.');
        $this->assertNotNull($this->find(DefinitionType::UpperUnit, 'Ticari Direktörlük'), 'A missing parent is created.');
        $this->assertSame('770.01', $this->find(DefinitionType::CostGroup, 'Genel Yönetim')?->extra['cost_center'] ?? null);

        // Second upload: matched by code; renamed and made passive. Unchanged rows stay unchanged.
        $import = $service->preview(ImportType::Definition, $this->firm, $this->file([
            ['Birim', 'İnsan Kaynakları Birimi', 'IK', 'Genel Müdürlük', 'Pasif', null],
            ['Üst Birim', 'Genel Müdürlük', 'GM', null, 'Aktif', null],
        ]), 'tanimlar.xlsx', $this->owner);
        $this->assertSame(['update', 'unchanged'], $import->rows->sortBy('row_number')->pluck('action')->all());
        $service->confirm($import, $this->owner);

        $unit = Definition::query()->ofType($this->firm, DefinitionType::Unit)->where('code', 'IK')->sole();
        $this->assertSame(['İnsan Kaynakları Birimi', false], [$unit->name, $unit->is_active]);
        $this->assertSame(6, Definition::where('firm_id', $this->firm->id)->count());
    }

    public function test_page_template_and_permission(): void
    {
        $this->get(route('definitions.index'))->assertOk()->assertSee(route('imports.create', 'tanim'), false);
        $this->get(route('imports.create', 'tanim'))->assertOk()->assertSee('Tüm tanım türleri tek sayfada yüklenir');
        $this->get(route('imports.template', 'tanim'))->assertOk()->assertDownload('HRD_Tanim_Sablonu.xlsx');

        $viewer = User::factory()->create();
        app(GrantAccess::class)->handle($viewer, $this->firm, [Permission::FirmView]);
        $this->actingAs($viewer);
        $this->get(route('imports.create', 'tanim'))->assertForbidden();
    }
}
