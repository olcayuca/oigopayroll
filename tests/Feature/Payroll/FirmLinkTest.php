<?php

namespace Tests\Feature\Payroll;

use App\Actions\Access\GrantAccess;
use App\Actions\Firms\ManageFirmLink;
use App\Enums\Permission;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Validation\ValidationException;

class FirmLinkTest extends PayrollTestCase
{
    private Firm $manager;

    private Firm $managed;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Firm::factory()->create(['name' => 'Muhasebe A.Ş.']);
        $this->managed = Firm::factory()->create(['name' => 'Müşteri Ltd.']);
        $this->accountant = User::factory()->create();

        app(GrantAccess::class)->handle($this->accountant, $this->manager, [
            Permission::CompanyView, Permission::CompanyCreate, Permission::WorkplaceView, Permission::WorkplaceViewCredentials,
        ]);
    }

    private function link(array $permissions, ?Firm $manager = null, ?Firm $managed = null): void
    {
        app(ManageFirmLink::class)->link($manager ?? $this->manager, $managed ?? $this->managed, $permissions, User::factory()->superAdmin()->create());
        $this->accountant->flushAccessCache();
    }

    public function test_manager_firm_users_reach_managed_firm_with_intersected_permissions(): void
    {
        $workplace = Workplace::factory()->create(['company_id' => Company::factory()->for($this->managed)]);

        $this->assertFalse($this->accountant->canSee($this->managed));

        $this->link([Permission::CompanyView, Permission::CompanyCreate, Permission::PayrollView]);

        $this->assertTrue($this->accountant->canSee($this->managed));
        $this->assertTrue($this->accountant->canSee($workplace));
        $this->assertTrue($this->accountant->can('create', [Company::class, $this->managed]));
        $this->assertTrue($this->accountant->hasPermissionOn(Permission::CompanyView, $workplace->company));

        // Link allows it, but the user does not hold it on their own firm.
        $this->assertFalse($this->accountant->hasPermissionOn(Permission::PayrollView, $this->managed));
        // User holds it, but the link does not allow it.
        $this->assertFalse($this->accountant->hasPermissionOn(Permission::WorkplaceViewCredentials, $workplace));

        $this->assertEqualsCanonicalizing([$this->manager->id, $this->managed->id], Firm::visibleTo($this->accountant)->pluck('id')->all());
    }

    public function test_links_are_not_transitive_and_need_an_active_manager_and_firm_level_grant(): void
    {
        $third = Firm::factory()->create();
        $this->link([Permission::CompanyView]);
        $this->link([Permission::CompanyView], $this->managed, $third);

        $this->assertTrue($this->accountant->canSee($this->managed));
        $this->assertFalse($this->accountant->canSee($third), 'A → B → C does not give A access to C.');

        $this->manager->update(['status' => 'passive']);
        $this->accountant->flushAccessCache();
        $this->assertFalse($this->accountant->canSee($this->managed));

        // A company-level grant in the manager firm does not travel through the link.
        $this->manager->update(['status' => 'active']);
        $companyOnly = User::factory()->create();
        app(GrantAccess::class)->handle($companyOnly, Company::factory()->for($this->manager)->create(), [Permission::CompanyView]);
        $this->assertFalse($companyOnly->canSee($this->managed));
    }

    public function test_managed_firm_owner_links_with_own_permissions_only(): void
    {
        $owner = User::factory()->create();
        app(GrantAccess::class)->handle($owner, $this->managed, [Permission::FirmManageUsers, Permission::CompanyView]);

        try {
            app(ManageFirmLink::class)->link($this->manager, $this->managed, [Permission::CompanyView, Permission::CompanyDelete], $owner, delegated: true);
            $this->fail('Owner granted a permission they do not hold.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('permissions', $e->errors());
        }

        $link = app(ManageFirmLink::class)->link($this->manager, $this->managed, [Permission::CompanyView], $owner, delegated: true);
        $this->assertSame(['company.view'], $link->permissions);

        // Someone who reaches the firm only through a link may not manage its links.
        $this->link([Permission::FirmManageUsers, Permission::CompanyView]);
        app(GrantAccess::class)->handle($this->accountant, $this->manager, [Permission::FirmManageUsers, Permission::CompanyView]);
        $this->accountant->flushAccessCache();

        $this->expectException(ValidationException::class);
        app(ManageFirmLink::class)->link(Firm::factory()->create(), $this->managed, [Permission::CompanyView], $this->accountant, delegated: true);
    }

    public function test_a_firm_cannot_manage_itself(): void
    {
        $this->expectException(ValidationException::class);

        $this->link([Permission::CompanyView], $this->manager, $this->manager);
    }
}
