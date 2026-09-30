<?php

namespace Tests\Feature\Admin;

use App\Enums\CodeList;
use App\Enums\Portal;
use App\Models\PayrollCode;
use App\Models\User;
use Database\Seeders\PayrollCodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PayrollCodesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());
    }

    public function test_seeded_lists(): void
    {
        $this->seed(PayrollCodeSeeder::class);
        $this->seed(PayrollCodeSeeder::class); // idempotent

        $this->assertSame(45, PayrollCode::where('list', CodeList::DocumentTypes)->count());
        $this->assertSame('Kısmi istihdam', PayrollCode::where('list', CodeList::MissingDayReasons)->where('code', '06')->value('name'));
        $this->assertFalse(PayrollCode::where('list', CodeList::TerminationReasons)->where('code', '29')->value('is_active'));
        $this->assertStringContainsString('25/II-g', PayrollCode::where('list', CodeList::TerminationReasons)->where('code', '48')->value('name'));
        $this->assertNotContains('28', PayrollCode::options(CodeList::MissingDayReasons)->pluck('code')->all(), 'Inactive codes are not offered.');
    }

    public function test_page_tabs_and_crud(): void
    {
        $this->seed(PayrollCodeSeeder::class);

        $this->get(route('admin.codes.index'))->assertOk()->assertSee('role="tablist"', false)->assertSee('Tüm sigorta kollarına');
        $this->get(route('admin.codes.index', ['sekme' => 'isten-cikis']))->assertOk()->assertSee('Mevsim bitimi');

        Livewire::test('pages::admin.codes.index')
            ->set('tab', 'tesvik')
            ->call('create')
            ->set('code', '5746')
            ->set('name', 'Ar-Ge')
            ->call('save')
            ->assertHasErrors('code')
            ->set('code', '05510')
            ->call('save')
            ->assertHasErrors('code') // already exists
            ->set('code', '05746')
            ->call('save')
            ->assertHasNoErrors();

        $entry = PayrollCode::where('list', CodeList::IncentiveLaws)->where('code', '05746')->sole();

        Livewire::test('pages::admin.codes.index')->set('tab', 'tesvik')->call('toggle', $entry->id);
        $this->assertFalse($entry->refresh()->is_active);

        Livewire::test('pages::admin.codes.index')->set('tab', 'tesvik')->call('delete', $entry->id);
        $this->assertModelMissing($entry);
    }

    public function test_excel_import_restores_codes_and_reports_bad_rows(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Meslek Kodu', 'Meslek Adı'],
            [2411.1, 'Muhasebeci'],        // Excel number: must become 2411.10
            ['2411.01', 'Mali müşavir'],
            ['abc', 'Hatalı'],
            ['2512.03', ''],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'meslek').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $page = Livewire::test('pages::admin.codes.index')
            ->set('tab', 'meslek')
            ->set('file', UploadedFile::fake()->createWithContent('meslek.xlsx', (string) file_get_contents($path)))
            ->call('import');

        $result = $page->get('importResult');
        $this->assertSame(2, $result['created']);
        $this->assertCount(2, $result['errors']);
        $this->assertSame(['2411.01', '2411.10'], PayrollCode::where('list', CodeList::Occupations)->orderBy('code')->pluck('code')->all());
        $page->assertSee('2 yeni');
    }

    public function test_non_admins_cannot_manage_codes(): void
    {
        $this->actingAs(User::factory()->payrollSpecialist()->create());

        Livewire::test('pages::admin.codes.index')->assertForbidden();
    }
}
