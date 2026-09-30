<?php

namespace Tests\Feature;

use App\Actions\Access\GrantAccess;
use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<list<mixed>>
     */
    private function sheet(TestResponse $response): array
    {
        $response->assertOk();
        $file = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $file);

        return IOFactory::load($file->getFile()->getPathname())->getActiveSheet()->toArray();
    }

    public function test_panel_exports_only_the_active_firms_visible_records_without_secrets(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $firm = Firm::factory()->create();
        $company = Company::factory()->for($firm)->create(['company_no' => '007', 'title' => 'Bizim Şirket']);
        Workplace::factory()->create([
            'company_id' => $company->id,
            'branch_name' => 'Merkez Şube',
            'sgk_workplace_password' => 'cok-gizli-sifre',
            'opening_date' => '2021-03-15',
        ]);
        Company::factory()->for(Firm::factory())->create(['title' => 'Başka Firma Şirketi']);

        $owner = User::factory()->create();
        app(GrantAccess::class)->handle($owner, $firm, Permission::firmOwnerDefaults());
        $this->actingAs($owner);

        $this->get(route('companies.index'))->assertSee('Excel İndir');

        $companies = $this->sheet($this->get(route('exports.download', 'sirketler')));
        $this->assertSame('Şirket Numarası', $companies[0][0]);
        $this->assertCount(2, $companies);
        $this->assertSame('007', $companies[1][0], 'Leading zeros survive as text.');
        $this->assertSame('Bizim Şirket', $companies[1][1]);

        $workplaceResponse = $this->get(route('exports.download', 'isyerleri'));
        $workplaces = $this->sheet($workplaceResponse);
        $headers = $workplaces[0];
        $this->assertContains('İşyeri Şube Adı', $headers);
        $this->assertNotContains('SGK İşyeri Şifresi', $headers);
        $this->assertNotContains('SGK Bildirge Kullanıcı Adı (TCKN)', $headers);
        $this->assertSame('Merkez Şube', $workplaces[1][array_search('İşyeri Şube Adı', $headers, true)]);
        $this->assertStringNotContainsString('cok-gizli-sifre', json_encode($workplaces, JSON_UNESCAPED_UNICODE) ?: '');

        $this->assertSame(2, AuditLog::where('event', AuditEvent::DataExported)->count());

        // Admin-only reports are out of reach from the panel host.
        $this->get('https://'.Portal::Admin->domain().'/raporlar/indir/firmalar')->assertRedirect();
    }

    public function test_admin_reports_page_and_downloads(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->onPortal(Portal::Admin);
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);
        $firm = Firm::factory()->create(['name' => 'Rapor Firması']);
        Workplace::factory()->create(['company_id' => Company::factory()->for($firm)]);

        $this->get(route('admin.reports.index'))->assertOk()->assertSee('role="tablist"', false)->assertSee('Aktif firma');
        $this->get(route('admin.reports.index', ['sekme' => 'disa-aktarma']))->assertOk()->assertSee('İşlem Kayıtları');

        $firms = $this->sheet($this->get(route('admin.reports.download', 'firmalar')));
        $this->assertSame('Rapor Firması', $firms[1][0]);
        $this->assertEquals(1, $firms[1][array_search('İşyeri Sayısı', $firms[0], true)]);

        $users = $this->sheet($this->get(route('admin.reports.download', 'kullanicilar')));
        $this->assertSame($admin->email, $users[1][1]);

        $workplaces = $this->sheet($this->get(route('admin.reports.download', 'isyerleri')));
        $this->assertSame('Firma', $workplaces[0][0]);
        $this->assertSame('Rapor Firması', $workplaces[1][0]);

        $this->get(route('admin.reports.download', 'islem-kayitlari'))->assertSessionHasErrors('baslangic');
        $logs = $this->sheet($this->get(route('admin.reports.download', [
            'report' => 'islem-kayitlari', 'baslangic' => now()->subDay()->toDateString(), 'bitis' => now()->toDateString(),
        ])));
        $this->assertGreaterThanOrEqual(2, count($logs));

        $this->get(route('admin.reports.download', [
            'report' => 'islem-kayitlari', 'baslangic' => '2020-01-01', 'bitis' => now()->toDateString(),
        ]))->assertStatus(422);
    }
}
