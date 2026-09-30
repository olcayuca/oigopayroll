<?php

namespace Tests\Feature;

use App\Actions\Access\GrantAccess;
use App\Actions\Companies\SaveCompany;
use App\Actions\Workplaces\SaveWorkplace;
use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrashTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private Company $company;

    private Workplace $workplace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->firm = Firm::factory()->create();
        $this->company = Company::factory()->for($this->firm)->create(['title' => 'Silinen Şirket A.Ş.']);
        $this->workplace = Workplace::factory()->create(['company_id' => $this->company->id, 'branch_name' => 'Silinen Şube']);
    }

    private function trashBoth(User $actor): void
    {
        $this->actingAs($actor);
        app(SaveWorkplace::class)->delete($this->workplace);
        app(SaveCompany::class)->delete($this->company->fresh());
    }

    public function test_panel_user_restores_in_hierarchy_order(): void
    {
        $owner = User::factory()->create(['name' => 'Firma Sahibi']);
        app(GrantAccess::class)->handle($owner, $this->firm, Permission::firmOwnerDefaults());
        $this->trashBoth($owner);

        $this->get(route('trash.index'))->assertOk()->assertSee('role="tablist"', false)
            ->assertSee('Silinen Şirket A.Ş.')->assertSee('Firma Sahibi')->assertDontSee('Kalıcı sil');

        // The workplace is hidden while its company is in the trash.
        $this->get(route('trash.index', ['sekme' => 'isyerleri']))->assertOk()->assertDontSee('Silinen Şube');

        Livewire::test('pages::trash.index')
            ->call('restore', 'workplace', $this->workplace->id); // blocked: company still deleted
        $this->assertTrue($this->workplace->fresh()?->trashed());

        Livewire::test('pages::trash.index')
            ->call('restore', 'company', $this->company->id)
            ->set('tab', 'isyerleri')
            ->assertSee('Silinen Şube')
            ->call('restore', 'workplace', $this->workplace->id);

        $this->assertFalse($this->company->fresh()?->trashed());
        $this->assertFalse($this->workplace->fresh()?->trashed());
        $this->assertSame(2, AuditLog::whereIn('event', [AuditEvent::CompanyRestored, AuditEvent::WorkplaceRestored])->count());

        Livewire::test('pages::trash.index')->call('purge', 'company', $this->company->id)->assertForbidden();
    }

    public function test_restore_needs_delete_permission_and_stays_inside_the_firm(): void
    {
        $owner = User::factory()->create();
        app(GrantAccess::class)->handle($owner, $this->firm, Permission::firmOwnerDefaults());
        $this->trashBoth($owner);

        $viewer = User::factory()->create();
        app(GrantAccess::class)->handle($viewer, $this->firm, [Permission::FirmView, Permission::CompanyView]);
        $this->actingAs($viewer);
        $this->get(route('trash.index'))->assertOk()->assertSee('Silinen Şirket A.Ş.')->assertDontSee('Geri al');
        Livewire::test('pages::trash.index')->call('restore', 'company', $this->company->id)->assertForbidden();

        // Another firm's owner cannot even find it.
        $stranger = User::factory()->create();
        app(GrantAccess::class)->handle($stranger, Firm::factory()->create(), Permission::firmOwnerDefaults());
        $this->actingAs($stranger);
        $this->get(route('trash.index'))->assertOk()->assertDontSee('Silinen Şirket A.Ş.');
        Livewire::test('pages::trash.index')->call('restore', 'company', $this->company->id)->assertNotFound();

        $this->assertTrue($this->company->fresh()?->trashed());
    }

    public function test_admin_sees_all_firms_and_purges(): void
    {
        $this->onPortal(Portal::Admin);
        $admin = User::factory()->superAdmin()->create();
        $specialist = User::factory()->payrollSpecialist()->create();
        app(GrantAccess::class)->handle($specialist, $this->workplace, [Permission::WorkplaceView]);
        $this->trashBoth($admin);

        $this->get(route('admin.trash.index'))->assertOk()->assertSee($this->firm->name)->assertSee('Kalıcı sil');

        Livewire::test('pages::trash.index')
            ->assertSet('admin', true)
            ->call('purge', 'company', $this->company->id); // blocked: its workplace is still in the trash
        $this->assertNotNull(Company::withTrashed()->find($this->company->id));

        Livewire::test('pages::trash.index')
            ->call('purge', 'workplace', $this->workplace->id)
            ->call('purge', 'company', $this->company->id);

        $this->assertNull(Company::withTrashed()->find($this->company->id));
        $this->assertNull(Workplace::withTrashed()->find($this->workplace->id));
        $this->assertSame(0, AccessGrant::where('user_id', $specialist->id)->count());
        $this->assertSame(2, AuditLog::where('event', AuditEvent::RecordPurged)->count());
    }
}
