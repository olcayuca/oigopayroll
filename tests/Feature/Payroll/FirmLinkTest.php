<?php

namespace Tests\Feature\Payroll;

use App\Actions\Access\GrantAccess;
use App\Actions\Firms\CreateSubFirm;
use App\Actions\Firms\ManageFirmLink;
use App\Enums\FirmStatus;
use App\Enums\Permission;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Firm;
use App\Models\FirmLink;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Validation\ValidationException;

class FirmLinkTest extends PayrollTestCase
{
    private Firm $manager;

    private Firm $managed;

    private User $boss;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Firm::factory()->create(['name' => 'Muhasebe A.Ş.']);
        $this->managed = Firm::factory()->create(['name' => 'Müşteri Ltd.']);

        // Manager firm's own users.
        $this->boss = User::factory()->create();
        app(GrantAccess::class)->handle($this->boss, $this->manager, Permission::firmOwnerDefaults());

        $this->accountant = User::factory()->create();
        app(GrantAccess::class)->handle($this->accountant, $this->manager, [Permission::CompanyView, Permission::CompanyCreate]);
    }

    private function link(array $permissions): FirmLink
    {
        return app(ManageFirmLink::class)->link($this->manager, $this->managed, $permissions, User::factory()->superAdmin()->create());
    }

    public function test_users_belong_to_one_firm_and_cannot_be_granted_elsewhere(): void
    {
        $this->assertSame($this->manager->id, $this->accountant->firm_id, 'First grant sets the home firm.');

        $this->expectException(ValidationException::class);
        app(GrantAccess::class)->handle($this->accountant, $this->managed, [Permission::CompanyView]);
    }

    public function test_a_link_alone_gives_nobody_access(): void
    {
        $this->link([Permission::CompanyView]);

        $this->assertFalse($this->boss->fresh()->canSee($this->managed));
        $this->assertFalse($this->accountant->fresh()->canSee($this->managed));
    }

    public function test_manager_firm_assigns_its_own_users_within_link_permissions(): void
    {
        $link = $this->link([Permission::CompanyView, Permission::CompanyCreate, Permission::WorkplaceView]);
        $workplace = Workplace::factory()->create(['company_id' => Company::factory()->for($this->managed)]);

        app(ManageFirmLink::class)->assign($link, $this->accountant, $this->managed, [Permission::CompanyView, Permission::CompanyCreate], null, $this->boss);

        $accountant = $this->accountant->fresh();
        $this->assertTrue($accountant->canSee($this->managed));
        $this->assertTrue($accountant->canSee($workplace));
        $this->assertTrue($accountant->can('create', [Company::class, $this->managed]));
        $this->assertEqualsCanonicalizing([$this->manager->id, $this->managed->id], Firm::visibleTo($accountant)->pluck('id')->all());

        // Beyond the link: rejected.
        try {
            app(ManageFirmLink::class)->assign($link, $this->accountant, $this->managed, [Permission::CompanyDelete], null, $this->boss);
            $this->fail('Assigned a permission the link does not allow.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('permissions', $e->errors());
        }

        // Tightening the link caps existing assignments immediately.
        $this->link([Permission::CompanyView]);
        $this->assertFalse($this->accountant->fresh()->can('create', [Company::class, $this->managed]));
        $this->assertTrue($this->accountant->fresh()->canSee($this->managed));
    }

    public function test_only_own_firm_users_can_be_assigned_and_only_by_the_manager_firm(): void
    {
        $link = $this->link([Permission::CompanyView]);
        $stranger = User::factory()->create();
        app(GrantAccess::class)->handle($stranger, Firm::factory()->create(), [Permission::CompanyView]);

        foreach ([
            fn () => app(ManageFirmLink::class)->assign($link, $stranger, $this->managed, [Permission::CompanyView], null, $this->boss),
            fn () => app(ManageFirmLink::class)->assign($link, $this->boss, $this->managed, [Permission::CompanyView], null, $this->accountant),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Assignment should have been rejected.');
            } catch (ValidationException) {
                //
            }
        }

        $this->assertSame(0, app(ManageFirmLink::class)->assignmentsQuery($link)->count());
    }

    public function test_unlinking_removes_assignments_and_passive_manager_suspends_them(): void
    {
        $link = $this->link([Permission::CompanyView]);
        app(ManageFirmLink::class)->assign($link, $this->accountant, $this->managed, [Permission::CompanyView], null, $this->boss);

        $this->manager->update(['status' => FirmStatus::Passive]);
        $this->assertFalse($this->accountant->fresh()->canSee($this->managed));
        $this->manager->update(['status' => FirmStatus::Active]);
        $this->assertTrue($this->accountant->fresh()->canSee($this->managed));

        app(ManageFirmLink::class)->unlink($link, User::factory()->superAdmin()->create());

        $this->assertSame(0, AccessGrant::where('user_id', $this->accountant->id)->where('scope_id', $this->managed->id)->where('scope_type', 'firm')->count());
        $this->assertFalse($this->accountant->fresh()->canSee($this->managed));
    }

    public function test_links_are_not_transitive(): void
    {
        $third = Firm::factory()->create();
        $link = $this->link([Permission::CompanyView]);
        app(ManageFirmLink::class)->link($this->managed, $third, [Permission::CompanyView], User::factory()->superAdmin()->create());
        app(ManageFirmLink::class)->assign($link, $this->accountant, $this->managed, [Permission::CompanyView], null, $this->boss);

        $this->assertTrue($this->accountant->fresh()->canSee($this->managed));
        $this->assertFalse($this->accountant->fresh()->canSee($third));
    }

    public function test_managed_firm_member_links_with_own_permissions_only(): void
    {
        $owner = User::factory()->create();
        app(GrantAccess::class)->handle($owner, $this->managed, [Permission::FirmManageUsers, Permission::CompanyView]);

        try {
            app(ManageFirmLink::class)->link($this->manager, $this->managed, [Permission::CompanyDelete], $owner, delegated: true);
            $this->fail('Owner allowed a permission they do not hold.');
        } catch (ValidationException) {
            //
        }

        $link = app(ManageFirmLink::class)->link($this->manager, $this->managed, [Permission::CompanyView], $owner, delegated: true);
        $this->assertSame(['company.view'], $link->permissions);

        // A manager-firm user cannot manage the managed firm's links.
        $this->expectException(ValidationException::class);
        app(ManageFirmLink::class)->unlink($link, $this->boss, delegated: true);
    }

    public function test_sub_firm_is_pending_linked_and_followed_by_its_creator(): void
    {
        $sub = app(CreateSubFirm::class)->handle($this->manager, ['name' => 'Şube Ltd.'], $this->boss);

        $this->assertSame(FirmStatus::Pending, $sub->status);
        $this->assertSame($this->manager->id, $sub->parent_firm_id);
        $this->assertSame(1, FirmLink::where('manager_firm_id', $this->manager->id)->where('managed_firm_id', $sub->id)->count());
        $this->assertTrue($this->boss->fresh()->canSee($sub));
        $this->assertSame($this->manager->id, $this->boss->fresh()->firm_id, 'Creator still belongs to the parent firm.');

        // Without the sub-firm permission it is refused.
        $this->expectException(ValidationException::class);
        app(CreateSubFirm::class)->handle($this->manager, ['name' => 'Olmaz'], $this->accountant);
    }

    public function test_a_firm_cannot_manage_itself(): void
    {
        $this->expectException(ValidationException::class);

        app(ManageFirmLink::class)->link($this->manager, $this->manager, [Permission::CompanyView], $this->boss);
    }
}
