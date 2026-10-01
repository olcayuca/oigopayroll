<?php

namespace Tests\Feature\Payroll;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Imports\ExcelTemplateBuilder;
use App\Imports\ImportColumns;
use App\Imports\ImportService;
use App\Models\Company;
use App\Models\Firm;
use App\Models\Workplace;
use App\Support\TurkishIdentifiers;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelImportTest extends PayrollTestCase
{
    public function test_template_has_all_columns_dropdowns_and_help(): void
    {
        $path = app(ExcelTemplateBuilder::class)->build(ImportType::Workplace);
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);

        $this->assertSame(['İşyerleri', 'Listeler', 'Açıklamalar'], $spreadsheet->getSheetNames());
        $this->assertSame('Şirket Numarası *', $sheet->getCell('A1')->getValue());
        $this->assertCount(count(ImportColumns::for(ImportType::Workplace)), array_filter($sheet->rangeToArray('A1:BZ1')[0]));

        $hazardColumn = $this->columnOf($sheet->rangeToArray('A1:BZ1')[0], 'Tehlike Sınıfı *');
        $this->assertSame('list', $sheet->getCell($hazardColumn.'2')->getDataValidation()->getType());

        @unlink($path);
    }

    public function test_company_preview_reports_errors_and_blocks_confirmation(): void
    {
        $firm = Firm::factory()->create();
        Company::factory()->for($firm)->create(['company_no' => '5000']);

        $path = $this->fillTemplate(ImportType::Company, [
            $this->companyRow('1001'),
            $this->companyRow('1001'),                                   // duplicate in file
            $this->companyRow('5000'),                                   // already in the firm: an update
            [...$this->companyRow('1002'), 'Şirket Sektörü *' => 'Uzay'], // not in the list
            [...$this->companyRow('1003'), 'Vergi Numarası *' => ''],     // required
        ]);

        $import = app(ImportService::class)->preview(ImportType::Company, $firm, $path, 'sirketler.xlsx');

        $this->assertSame(5, $import->total_rows);
        $this->assertSame(3, $import->error_rows);
        $this->assertFalse($import->canBeConfirmed());

        $errors = $import->rows->pluck('errors', 'row_number');
        $this->assertNull($errors[2]);
        $this->assertStringContainsString('2. satırda', $errors[3]['company_no'][0]);
        $this->assertNull($errors[4]);
        $this->assertSame('update', $import->rows->firstWhere('row_number', 4)?->action);
        $this->assertSame(['Şirket Sektörü listede bulunamadı: "Uzay".'], $errors[5]['sector_id']);
        $this->assertArrayHasKey('tax_number', $errors[6]);

        $this->expectException(ValidationException::class);
        try {
            app(ImportService::class)->confirm($import);
        } finally {
            $this->assertSame(1, Company::count(), 'Nothing is created from a preview with errors.');
        }
    }

    public function test_clean_company_and_workplace_imports_create_records_on_confirm(): void
    {
        $firm = Firm::factory()->create();

        $companyImport = app(ImportService::class)->preview(ImportType::Company, $firm, $this->fillTemplate(ImportType::Company, [
            $this->companyRow('1001'),
            $this->companyRow('1002'),
        ]), 'sirketler.xlsx');

        $this->assertTrue($companyImport->canBeConfirmed());
        $this->assertSame(0, Company::count(), 'Preview does not create records.');

        app(ImportService::class)->confirm($companyImport);

        $this->assertSame(ImportStatus::Completed, $companyImport->status);
        $this->assertSame(2, $firm->companies()->count());
        $this->assertSame('anonim', $firm->companies()->first()?->company_type->value);

        $workplaceImport = app(ImportService::class)->preview(ImportType::Workplace, $firm, $this->fillTemplate(ImportType::Workplace, [
            $this->workplaceRow('1001', '1'),
            $this->workplaceRow('1002', '1'),
        ]), 'isyerleri.xlsx');

        $this->assertSame(0, $workplaceImport->error_rows, json_encode($workplaceImport->rows->pluck('errors')) ?: '');

        app(ImportService::class)->confirm($workplaceImport);

        $workplace = Workplace::query()->firstOrFail();
        $this->assertSame(2, Workplace::count());
        $this->assertSame(34, $workplace->province_id);
        $this->assertSame('2021-03-01', $workplace->opening_date->format('Y-m-d'));
        $this->assertSame('sgk-sifre', $workplace->sgk_workplace_password);
        $this->assertTrue($workplace->has_union);
        $this->assertSame(0, Company::withoutWorkplaces()->count());
    }

    public function test_workplace_rows_must_reference_a_company_of_the_same_firm(): void
    {
        $firm = Firm::factory()->create();
        Company::factory()->create(['company_no' => '9999']); // belongs to another firm

        $import = app(ImportService::class)->preview(ImportType::Workplace, $firm, $this->fillTemplate(ImportType::Workplace, [
            $this->workplaceRow('9999', '1'),
        ]), 'isyerleri.xlsx');

        $this->assertSame(1, $import->error_rows);
        $this->assertArrayHasKey('company_no', $import->rows->first()->errors ?? []);
    }

    public function test_missing_required_column_is_a_file_error(): void
    {
        $firm = Firm::factory()->create();
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([['Şirket Numarası', 'Şirket Adı'], ['1', 'X']]);
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $import = app(ImportService::class)->preview(ImportType::Company, $firm, $path, 'eksik.xlsx');

        $this->assertNotEmpty($import->file_errors);
        $this->assertFalse($import->canBeConfirmed());
    }

    public function test_preview_rows_are_stored_encrypted(): void
    {
        $firm = Firm::factory()->create();
        Company::factory()->for($firm)->create(['company_no' => '1001']);

        $import = app(ImportService::class)->preview(ImportType::Workplace, $firm, $this->fillTemplate(ImportType::Workplace, [
            $this->workplaceRow('1001', '1'),
        ]), 'isyerleri.xlsx');

        $raw = \DB::table('data_import_rows')->where('data_import_id', $import->id)->value('data');
        $this->assertStringNotContainsString('sgk-sifre', (string) $raw);
    }

    /**
     * @return array<string, string>
     */
    private function companyRow(string $companyNo): array
    {
        return [
            'Şirket Numarası *' => $companyNo,
            'Şirket Adı / Unvanı *' => "Şirket {$companyNo} A.Ş.",
            'Şirket Kısa Adı *' => "Şirket {$companyNo}",
            'Şirket Tipi *' => 'Anonim Şirket',
            'Şirket Sektörü *' => 'bilgi teknolojileri',
            'Vergi Numarası *' => TurkishIdentifiers::makeVkn('0'.str_pad($companyNo, 8, '0', STR_PAD_LEFT)),
            'Vergi Dairesi *' => 'Kadıköy',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function workplaceRow(string $companyNo, string $workplaceNo): array
    {
        return [
            'Şirket Numarası *' => $companyNo,
            'İşyeri Numarası *' => $workplaceNo,
            'İşyeri Şube Adı *' => 'Merkez',
            'İşyeri Tipi *' => 'Merkez İşyeri',
            'İşyeri Türü *' => 'Ar-Ge',
            'Ünvan *' => 'Örnek A.Ş.',
            'Vergi Numarası *' => TurkishIdentifiers::makeVkn('012345678'),
            'Vergi Dairesi *' => 'Kadıköy',
            'Tehlike Sınıfı *' => 'Çok Tehlikeli',
            'ÇSGB İşkolu' => 'Metal',
            'İl *' => 'İstanbul',
            'İlçe *' => 'Kadıköy',
            'İşyeri Açık Adresi *' => 'Moda Cad. No:1',
            'SGK İşyeri Yetkilisi Adı Soyadı *' => 'Ayşe Yılmaz',
            'SGK İşyeri Kodu *' => '000123',
            'e-Bildirge Yetkilisi Adı Soyadı *' => 'Ayşe Yılmaz',
            'İşyeri Açılış Tarihi *' => '01.03.2021',
            'SGK Bildirge Kullanıcı Adı (TCKN) *' => '10000000146',
            'SGK İşyeri Şifresi *' => 'sgk-sifre',
            'SGK Sistem Şifresi *' => 'sistem-sifre',
            'Sendikalı İşyeri' => 'Evet',
            'Sendika Adı' => 'Birleşik Metal-İş',
        ];
    }

    /**
     * Fill the generated template the way a user would, and save it.
     *
     * @param  list<array<string, string>>  $rows
     */
    private function fillTemplate(ImportType $type, array $rows): string
    {
        $path = app(ExcelTemplateBuilder::class)->build($type);
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);
        $headers = $sheet->rangeToArray('A1:BZ1')[0];

        foreach ($rows as $index => $row) {
            foreach ($row as $header => $value) {
                $sheet->setCellValueExplicit($this->columnOf($headers, $header).($index + 2), $value, DataType::TYPE_STRING);
            }
        }

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * @param  array<int, mixed>  $headers
     */
    private function columnOf(array $headers, string $header): string
    {
        $index = array_search($header, $headers, true);
        $this->assertNotFalse($index, "Header [{$header}] not found in template.");

        return Coordinate::stringFromColumnIndex((int) $index + 1);
    }
}
