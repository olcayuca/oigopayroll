<?php

namespace Tests\Feature\Payroll;

use App\Actions\Access\GrantAccess;
use App\Enums\ImportType;
use App\Enums\Permission;
use App\Exports\ExcelExporter;
use App\Exports\ExportType;
use App\Imports\ImportService;
use App\Models\Company;
use App\Models\DataImportRow;
use App\Models\District;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Bulk update: a file exported from the system (no credential columns) is edited and uploaded again.
 */
class ExcelUpdateTest extends PayrollTestCase
{
    private Firm $firm;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firm = Firm::factory()->create();
        $this->company = Company::factory()->for($this->firm)->create(['company_no' => '1001', 'title' => 'Eski Unvan A.Ş.']);
    }

    /**
     * Export the type for this firm, then let the callback edit the sheet.
     *
     * @param  ExportType::Companies|ExportType::Workplaces  $type
     * @param  callable(Worksheet, array<int, mixed>): void  $edit
     */
    /**
     * Address as the system stores it after a form or Excel save (ids, not typed names).
     *
     * @return array<string, mixed>
     */
    private static function address(): array
    {
        return ['province_id' => 34, 'province_name' => null, 'district_id' => District::where('name', 'Kadıköy')->value('id'), 'district_name' => null];
    }

    private function exportAndEdit(ExportType $type, callable $edit): string
    {
        $query = $type === ExportType::Companies
            ? $type->prepare(Company::query()->where('firm_id', $this->firm->id))
            : $type->prepare(Workplace::query()->whereIn('company_id', $this->firm->companies()->select('id')));

        $path = app(ExcelExporter::class)->write($type->label(), $type->columns(withFirm: false), $query->get())['path'];
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $edit($sheet, $sheet->rangeToArray('A1:BZ1')[0]);
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * @param  array<int, mixed>  $headers
     */
    private static function cell(array $headers, string $header, int $row): string
    {
        $index = array_search($header, $headers, true);
        self::assertNotFalse($index, "Header [{$header}] not found.");

        return Coordinate::stringFromColumnIndex((int) $index + 1).$row;
    }

    public function test_exported_workplaces_update_and_keep_credentials(): void
    {
        $workplace = Workplace::factory()->create([
            'company_id' => $this->company->id,
            'workplace_no' => '1',
            'branch_name' => 'Eski Şube',
            'sgk_workplace_password' => 'gizli-sgk',
            'sgk_system_password' => 'gizli-sistem',
            ...self::address(),
        ]);
        Workplace::factory()->create(['company_id' => $this->company->id, 'workplace_no' => '2', 'branch_name' => 'Aynı Kalan', ...self::address()]);

        $path = $this->exportAndEdit(ExportType::Workplaces, function ($sheet, $headers) {
            $sheet->setCellValueExplicit(self::cell($headers, 'İşyeri Şube Adı', 2), 'Yeni Şube Adı', DataType::TYPE_STRING);
        });

        $import = app(ImportService::class)->preview(ImportType::Workplace, $this->firm, $path, 'isyerleri.xlsx');

        $this->assertSame([], $import->file_errors ?? [], 'Credential columns may be missing.');
        $this->assertSame(0, $import->error_rows, json_encode($import->rows->pluck('errors')) ?: '');
        $this->assertSame([DataImportRow::UPDATE => 1, DataImportRow::UNCHANGED => 1], $import->actionCounts());

        app(ImportService::class)->confirm($import);

        $workplace->refresh();
        $this->assertSame('Yeni Şube Adı', $workplace->branch_name);
        $this->assertSame('gizli-sgk', $workplace->sgk_workplace_password);
        $this->assertSame('gizli-sistem', $workplace->sgk_system_password);
        $this->assertSame(2, Workplace::count());
        $this->assertSame(1, $import->refresh()->updated_rows);
        $this->assertSame(0, $import->created_rows);
    }

    public function test_new_rows_in_an_exported_file_still_need_credentials(): void
    {
        Workplace::factory()->create(['company_id' => $this->company->id, 'workplace_no' => '1']);

        $path = $this->exportAndEdit(ExportType::Workplaces, function ($sheet, $headers) {
            // Copy row 2 as a new workplace number 99.
            foreach ($headers as $index => $header) {
                $column = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValueExplicit($column.'3', (string) $sheet->getCell($column.'2')->getValue(), DataType::TYPE_STRING);
            }
            $sheet->setCellValueExplicit(self::cell($headers, 'İşyeri Numarası', 3), '99', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit(self::cell($headers, 'İşyeri SGK Sicil Numarası', 3), '', DataType::TYPE_STRING);
        });

        $import = app(ImportService::class)->preview(ImportType::Workplace, $this->firm, $path, 'isyerleri.xlsx');

        $errors = $import->rows->firstWhere('row_number', 3)?->errors ?? [];
        $this->assertArrayHasKey('sgk_workplace_password', $errors);
        $this->assertFalse($import->canBeConfirmed());
    }

    public function test_exported_companies_update_only_present_columns(): void
    {
        $this->company->update(['website' => 'https://eski.example.com']);

        $path = $this->exportAndEdit(ExportType::Companies, function ($sheet, $headers) {
            $sheet->setCellValueExplicit(self::cell($headers, 'Şirket Adı / Unvanı', 2), 'Yeni Unvan A.Ş.', DataType::TYPE_STRING);
            $sheet->removeColumn(Coordinate::stringFromColumnIndex(array_search('Web Adresi', $headers, true) + 1));
        });

        $import = app(ImportService::class)->preview(ImportType::Company, $this->firm, $path, 'sirketler.xlsx');
        $this->assertSame([DataImportRow::UPDATE => 1], $import->actionCounts());

        app(ImportService::class)->confirm($import);

        $this->company->refresh();
        $this->assertSame('Yeni Unvan A.Ş.', $this->company->title);
        $this->assertSame('https://eski.example.com', $this->company->website, 'Columns missing from the file keep their value.');
        $this->assertSame(1, Company::count());
    }

    public function test_records_outside_the_firm_or_without_update_permission_are_rejected(): void
    {
        $path = $this->exportAndEdit(ExportType::Companies, fn () => null);

        // Same company number, other firm.
        $other = Firm::factory()->create();
        $import = app(ImportService::class)->preview(ImportType::Company, $other, $path, 'sirketler.xlsx');
        $this->assertStringContainsString('başka bir firmada', $import->rows->first()->errors['company_no'][0] ?? '');

        // A user who may import but not update.
        $user = User::factory()->create();
        app(GrantAccess::class)->handle($user, $this->firm, [Permission::FirmView, Permission::CompanyView, Permission::CompanyImport, Permission::CompanyCreate]);
        $import = app(ImportService::class)->preview(ImportType::Company, $this->firm, $path, 'sirketler.xlsx', $user->fresh());
        $this->assertStringContainsString('güncelleme yetkiniz yok', $import->rows->first()->errors['company_no'][0] ?? '');

        // Trashed company.
        $this->company->delete();
        $import = app(ImportService::class)->preview(ImportType::Company, $this->firm, $path, 'sirketler.xlsx');
        $this->assertStringContainsString('çöp kutusunda', $import->rows->first()->errors['company_no'][0] ?? '');
    }

    public function test_upload_page_shows_planned_actions(): void
    {
        $owner = User::factory()->create();
        app(GrantAccess::class)->handle($owner, $this->firm, Permission::firmOwnerDefaults());
        $this->actingAs($owner);

        $path = $this->exportAndEdit(ExportType::Companies, function ($sheet, $headers) {
            $sheet->setCellValueExplicit(self::cell($headers, 'Şirket Kısa Adı', 2), 'Yeni Kısa', DataType::TYPE_STRING);
        });
        $import = app(ImportService::class)->preview(ImportType::Company, $this->firm, $path, 'sirketler.xlsx', $owner);

        Livewire::test('pages::panel.imports.upload', ['type' => 'sirket'])
            ->set('importId', $import->id)
            ->assertSee('Güncellenecek')
            ->assertSee('1 kayıt güncellenecek')
            ->call('confirm');

        $this->assertSame('Yeni Kısa', $this->company->fresh()?->short_name);
    }
}
