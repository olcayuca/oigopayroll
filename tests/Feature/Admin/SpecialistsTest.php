<?php

namespace Tests\Feature\Admin;

use App\Actions\Access\GrantAccess;
use App\Actions\Firms\AssignSpecialist;
use App\Enums\AuditEvent;
use App\Enums\FirmStatus;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;
use Database\Seeders\PermissionTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SpecialistsTest extends TestCase
{
    use RefreshDatabase;

    private function action(): AssignSpecialist
    {
        return app(AssignSpecialist::class);
    }

    public function test_assigning_grants_access_and_reassigning_moves_it(): void
    {
        $this->seed(PermissionTemplateSeeder::class);
        $firm = Firm::factory()->create();
        $company = Company::factory()->for($firm)->create();
        $ayse = User::factory()->payrollSpecialist()->create();
        $mehmet = User::factory()->payrollSpecialist()->create();

        $this->action()->handle($firm, $ayse);

        $this->assertSame($ayse->id, $firm->fresh()?->specialist_id);
        $this->assertTrue($ayse->fresh()?->hasPermissionOn(Permission::CompanyUpdate, $company));
        $this->assertFalse($ayse->fresh()?->hasPermissionOn(Permission::FirmManageUsers, $firm), 'Bordro Uzmanı template excludes user management.');

        $this->action()->handle($firm->fresh(), $mehmet);

        $this->assertSame($mehmet->id, $firm->fresh()?->specialist_id);
        $this->assertFalse($ayse->fresh()?->canSee($firm));
        $this->assertTrue($mehmet->fresh()?->canSee($company));
        $this->assertSame(2, AuditLog::where('event', AuditEvent::SpecialistAssigned)->count());

        // Unassigning removes the grant too.
        $this->action()->handle($firm->fresh(), null);
        $this->assertNull($firm->fresh()?->specialist_id);
        $this->assertFalse($mehmet->fresh()?->canSee($firm));
    }

    public function test_existing_wider_grant_is_kept(): void
    {
        $firm = Firm::factory()->create();
        $specialist = User::factory()->payrollSpecialist()->create();
        AccessGrant::create(['user_id' => $specialist->id, 'scope_type' => 'firm', 'scope_id' => $firm->id, 'permissions' => Permission::firmOwnerDefaults()]);

        $this->action()->handle($firm, $specialist);

        $this->assertTrue($specialist->fresh()?->hasPermissionOn(Permission::FirmManageUsers, $firm));
    }

    public function test_only_active_specialists_can_be_responsible(): void
    {
        $firm = Firm::factory()->create();

        foreach ([User::factory()->create(), User::factory()->superAdmin()->create(), User::factory()->payrollSpecialist()->inactive()->create()] as $user) {
            try {
                $this->action()->handle($firm, $user);
                $this->fail('Expected rejection for '.$user->type->value);
            } catch (ValidationException) {
                $this->assertNull($firm->fresh()?->specialist_id);
            }
        }
    }

    public function test_transfer_moves_all_firms(): void
    {
        $from = User::factory()->payrollSpecialist()->create();
        $to = User::factory()->payrollSpecialist()->create();
        $firms = Firm::factory()->count(3)->create();
        $firms->each(fn (Firm $firm) => $this->action()->handle($firm, $from));

        $this->assertSame(3, $this->action()->transfer($from, $to));

        $this->assertSame(3, Firm::where('specialist_id', $to->id)->count());
        $this->assertSame(0, AccessGrant::where('user_id', $from->id)->count());
        $this->assertSame(3, AccessGrant::where('user_id', $to->id)->count());
    }

    public function test_admin_page_shows_workload_and_assigns(): void
    {
        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());
        $specialist = User::factory()->payrollSpecialist()->create(['name' => 'Ayşe Uzman']);
        $other = User::factory()->payrollSpecialist()->create(['name' => 'Mehmet Uzman']);
        $firm = Firm::factory()->create(['name' => 'Acme']);
        Firm::factory()->create(['name' => 'Reddedilen', 'status' => FirmStatus::Rejected]);
        Company::factory()->for($firm)->create();

        $this->get(route('admin.specialists.index'))->assertOk()->assertSee('role="tablist"', false)
            ->assertSee('Ayşe Uzman')->assertSee('1 firmanın sorumlu uzmanı yok');
        $this->get(route('admin.specialists.index', ['sekme' => 'firmalar']))->assertOk()
            ->assertSee('Acme')->assertDontSee('Reddedilen');

        Livewire::test('pages::admin.specialists.index')
            ->call('assign', $firm->id, (string) $specialist->id)
            ->assertSet('tab', 'uzmanlar')
            ->call('openTransfer', $specialist->id)
            ->call('transfer')
            ->assertHasErrors('transferTo')
            ->set('transferTo', (string) $other->id)
            ->call('transfer')
            ->assertHasNoErrors();

        $this->assertSame($other->id, $firm->fresh()?->specialist_id);
        $this->get(route('admin.firms.show', $firm))->assertSee('Mehmet Uzman');
    }

    public function test_client_sees_responsible_specialist_on_dashboard(): void
    {
        $firm = Firm::factory()->create();
        $owner = User::factory()->create();
        app(GrantAccess::class)->handle($owner, $firm, Permission::firmOwnerDefaults());
        $this->action()->handle($firm, User::factory()->payrollSpecialist()->create(['name' => 'Ayşe Uzman']));

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('Sorumlu bordro uzmanınız')->assertSee('Ayşe Uzman');
    }
}
