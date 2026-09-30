<?php

namespace Tests\Feature\Payroll;

use App\Actions\Access\GrantAccess;
use App\Enums\Permission;
use App\Models\Company;
use App\Models\Firm;
use App\Models\PermissionTemplate;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Validation\ValidationException;

class ScopedAccessTest extends PayrollTestCase
{
    public function test_firm_grant_covers_its_companies_and_workplaces_only(): void
    {
        $user = User::factory()->payrollSpecialist()->create();
        $workplace = Workplace::factory()->create();
        $otherWorkplace = Workplace::factory()->create();
        $firm = $workplace->company->firm;

        app(GrantAccess::class)->handle($user, $firm, [Permission::WorkplaceUpdate, Permission::CompanyView]);

        $this->assertTrue($user->can('update', $workplace));
        $this->assertTrue($user->can('view', $workplace->company));
        $this->assertFalse($user->can('delete', $workplace));
        $this->assertFalse($user->can('update', $otherWorkplace));
        $this->assertFalse($user->can('view', $otherWorkplace));
    }

    public function test_workplace_grant_does_not_reach_sibling_workplaces_or_parent_operations(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        [$granted, $sibling] = Workplace::factory()->count(2)->for($company)->create();

        app(GrantAccess::class)->handle($user, $granted, [Permission::WorkplaceView, Permission::WorkplaceUpdate]);

        $this->assertTrue($user->can('update', $granted));
        $this->assertFalse($user->can('update', $sibling));
        $this->assertFalse($user->can('update', $company));

        // Parents are visible (for navigation) but siblings are not.
        $this->assertSame([$granted->id], Workplace::visibleTo($user)->pluck('id')->all());
        $this->assertSame([$company->id], Company::visibleTo($user)->pluck('id')->all());
        $this->assertSame([$company->firm_id], Firm::visibleTo($user)->pluck('id')->all());
    }

    public function test_two_users_on_same_firm_can_have_different_permissions(): void
    {
        $firm = Firm::factory()->create();
        $editor = User::factory()->create();
        $viewer = User::factory()->create();

        app(GrantAccess::class)->handle($editor, $firm, [Permission::CompanyCreate]);
        app(GrantAccess::class)->handle($viewer, $firm, [Permission::CompanyView]);

        $this->assertTrue($editor->can('create', [Company::class, $firm]));
        $this->assertFalse($viewer->can('create', [Company::class, $firm]));
    }

    public function test_template_permissions_are_combined_with_explicit_ones(): void
    {
        $firm = Firm::factory()->create();
        $user = User::factory()->payrollSpecialist()->create();
        $template = PermissionTemplate::create(['name' => 'Görüntüleme', 'permissions' => ['company.view', 'bogus.permission']]);

        $this->assertSame(['company.view'], $template->refresh()->permissions, 'Unknown permissions are dropped.');

        app(GrantAccess::class)->handle($user, $firm, [Permission::CompanyImport], $template);

        $this->assertTrue($user->hasPermissionOn(Permission::CompanyView, $firm));
        $this->assertTrue($user->hasPermissionOn(Permission::CompanyImport, $firm));
        $this->assertFalse($user->hasPermissionOn(Permission::CompanyCreate, $firm));
    }

    public function test_grant_needs_permissions_or_template(): void
    {
        $this->expectException(ValidationException::class);

        app(GrantAccess::class)->handle(User::factory()->create(), Firm::factory()->create(), []);
    }

    public function test_revoke_and_inactive_users_lose_access(): void
    {
        $firm = Firm::factory()->create();
        $user = User::factory()->create();
        app(GrantAccess::class)->handle($user, $firm, [Permission::FirmUpdate]);

        $this->assertTrue($user->can('update', $firm));

        $user->forceFill(['is_active' => false])->save();
        $this->assertFalse($user->can('update', $firm));
        $this->assertSame([], Firm::visibleTo($user)->pluck('id')->all());

        $user->forceFill(['is_active' => true])->save();
        app(GrantAccess::class)->revoke($user, $firm);
        $this->assertFalse($user->can('update', $firm));
    }

    public function test_super_admin_sees_and_does_everything(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $workplace = Workplace::factory()->create();

        $this->assertTrue($admin->can('viewCredentials', $workplace));
        $this->assertTrue($admin->can('delete', $workplace->company));
        $this->assertSame(1, Workplace::visibleTo($admin)->count());
    }

    public function test_user_type_is_not_mass_assignable(): void
    {
        $user = User::create(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret123', 'type' => 'super_admin']);

        $this->assertFalse($user->refresh()->isSuperAdmin());
    }
}
