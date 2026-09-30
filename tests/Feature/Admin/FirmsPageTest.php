<?php

namespace Tests\Feature\Admin;

use App\Actions\Access\GrantAccess;
use App\Enums\FirmStatus;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FirmsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->onPortal(Portal::Admin);
    }

    public function test_admin_pages_render(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $firm = Firm::factory()->pending()->create(['name' => 'Acme Lojistik']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Onay bekleyen firma');
        $this->actingAs($admin)->get(route('admin.firms.index'))->assertOk()->assertSee('Acme Lojistik')->assertSee('Onay Bekliyor');
        $this->actingAs($admin)->get(route('admin.firms.show', $firm))->assertOk()->assertSee('Acme Lojistik');
    }

    public function test_super_admin_creates_an_active_firm(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test('pages::admin.firms.index')
            ->set('name', 'Beta Holding')
            ->call('createFirm')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertSame(FirmStatus::Active, Firm::where('name', 'Beta Holding')->value('status'));
    }

    public function test_firm_name_is_required(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test('pages::admin.firms.index')
            ->call('createFirm')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_pending_firm_can_be_approved_or_rejected_with_reason(): void
    {
        $this->actingAs($admin = User::factory()->superAdmin()->create());
        [$first, $second] = Firm::factory()->pending()->count(2)->create();

        Livewire::test('pages::admin.firms.index')->call('approve', $first->id);
        $this->assertSame(FirmStatus::Active, $first->refresh()->status);
        $this->assertSame($admin->id, $first->reviewed_by);

        Livewire::test('pages::admin.firms.index')
            ->call('startRejecting', $second->id)
            ->call('reject')
            ->assertHasErrors(['rejectionReason' => 'required'])
            ->set('rejectionReason', 'Vergi levhası eksik')
            ->call('reject')
            ->assertHasNoErrors();

        $this->assertSame(FirmStatus::Rejected, $second->refresh()->status);
        $this->assertSame('Vergi levhası eksik', $second->rejection_reason);
    }

    public function test_status_filter_and_search(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        Firm::factory()->pending()->create(['name' => 'Bekleyen A.Ş.']);
        Firm::factory()->create(['name' => 'Aktif Ltd.']);

        Livewire::test('pages::admin.firms.index')
            ->set('status', 'pending')
            ->assertSee('Bekleyen A.Ş.')
            ->assertDontSee('Aktif Ltd.')
            ->set('status', '')
            ->set('search', 'Aktif')
            ->assertSee('Aktif Ltd.')
            ->assertDontSee('Bekleyen A.Ş.');
    }

    public function test_payroll_specialist_only_sees_granted_firms_and_cannot_review_or_create(): void
    {
        $specialist = User::factory()->payrollSpecialist()->create();
        $granted = Firm::factory()->pending()->create(['name' => 'Yetkili Firma']);
        $other = Firm::factory()->pending()->create(['name' => 'Başka Firma']);
        app(GrantAccess::class)->handle($specialist, $granted, [Permission::FirmView]);

        $this->actingAs($specialist);

        Livewire::test('pages::admin.firms.index')
            ->assertSee('Yetkili Firma')
            ->assertDontSee('Başka Firma')
            ->assertDontSee('Yeni Firma')
            ->call('approve', $granted->id)
            ->assertForbidden();

        $this->assertSame(FirmStatus::Pending, $granted->refresh()->status);

        $this->get(route('admin.firms.show', $other))->assertForbidden();

        Livewire::test('pages::admin.firms.index')->set('name', 'X')->call('createFirm')->assertForbidden();
    }

    public function test_firm_detail_lists_companies_and_flags_missing_workplaces(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $company = Company::factory()->create(['title' => 'Örnek Teknoloji A.Ş.']);

        $this->get(route('admin.firms.show', $company->firm))
            ->assertOk()
            ->assertSee('Örnek Teknoloji A.Ş.')
            ->assertSee('İşyeri yok');
    }

    public function test_client_users_cannot_open_admin_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.firms.index'))
            ->assertRedirect('/login');
    }
}
