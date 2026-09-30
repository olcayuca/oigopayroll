<?php

namespace Tests\Feature\Admin;

use App\Actions\Access\GrantAccess;
use App\Enums\FirmSource;
use App\Enums\FirmStatus;
use App\Enums\ImportType;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Imports\ExcelTemplateBuilder;
use App\Models\Firm;
use App\Models\User;
use App\Support\TurkishIdentifiers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class FirmImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());
    }

    public function test_template_and_page(): void
    {
        $this->get(route('admin.firms.import'))->assertOk()->assertSee('Excel Şablonunu İndir');
        $this->get(route('admin.firms.template'))->assertOk()->assertDownload('HRD_Firma_Sablonu.xlsx');
    }

    public function test_errors_block_confirmation_and_clean_file_creates_active_firms(): void
    {
        $vknA = TurkishIdentifiers::makeVkn('100000001');
        $vknB = TurkishIdentifiers::makeVkn('100000002');
        Firm::factory()->create(['tax_number' => $vknB]);

        Livewire::test('pages::admin.firms.import')
            ->set('file', $this->sheet([
                ['Alfa', 'Alfa Ltd.', $vknA],
                ['Beta', 'Beta A.Ş.', $vknA],        // duplicate in file
                ['Gama', 'Gama A.Ş.', $vknB],        // already in the system
                ['', 'Adsız', ''],                   // name required
                ['Delta', 'Delta A.Ş.', '1234567891'], // invalid VKN
            ]))
            ->call('upload')
            ->assertSee('4 satırda hata var')
            ->assertDontSee('Onayla ve Oluştur');

        $this->assertSame(1, Firm::count());

        Livewire::test('pages::admin.firms.import')
            ->set('file', $this->sheet([
                ['Alfa', 'Alfa Ltd.', $vknA],
                ['Epsilon', '', ''],
            ]))
            ->call('upload')
            ->assertSee('Tüm satırlar geçerli')
            ->call('confirm')
            ->assertRedirect(route('admin.firms.index'));

        $alfa = Firm::where('name', 'Alfa')->firstOrFail();
        $this->assertSame(FirmStatus::Active, $alfa->status);
        $this->assertSame(FirmSource::Hrd, $alfa->source);
        $this->assertSame($vknA, $alfa->tax_number);
        $this->assertTrue(Firm::where('name', 'Epsilon')->exists());
    }

    public function test_panel_cannot_use_firm_import(): void
    {
        $this->onPortal(Portal::Panel);
        $client = User::factory()->create();
        $firm = Firm::factory()->create();
        app(GrantAccess::class)->handle($client, $firm, Permission::firmOwnerDefaults());

        $this->actingAs($client)->get(route('imports.create', 'firma'))->assertNotFound();
        $this->actingAs($client)->get(route('imports.template', 'firma'))->assertNotFound();
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $rows  name, title, tax number
     */
    private function sheet(array $rows): UploadedFile
    {
        $path = app(ExcelTemplateBuilder::class)->build(ImportType::Firm);
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);

        foreach ($rows as $r => $values) {
            foreach ($values as $c => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($c + 1).($r + 2), $value, DataType::TYPE_STRING);
            }
        }

        (new Xlsx($spreadsheet))->save($path);

        return UploadedFile::fake()->createWithContent('firmalar.xlsx', (string) file_get_contents($path));
    }
}
