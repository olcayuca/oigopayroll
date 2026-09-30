<?php

namespace Tests\Feature\Panel;

use App\Actions\Access\GrantAccess;
use App\Enums\Permission;
use App\Enums\ScopeType;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Firm;
use App\Models\PermissionTemplate;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class FirmUsersPageTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firm = Firm::factory()->create();
        $this->owner = User::factory()->create(['name' => 'Firma Sahibi']);
        app(GrantAccess::class)->handle($this->owner, $this->firm, Permission::firmOwnerDefaults());
        $this->actingAs($this->owner);
    }

    public function test_owner_adds_a_new_user_limited_to_one_workplace(): void
    {
        $workplace = Workplace::factory()->create(['company_id' => Company::factory()->for($this->firm)]);

        $this->get(route('users.index'))->assertOk()->assertSee('Firma Sahibi');

        $component = Livewire::test('pages::panel.users.index')
            ->call('newUser')
            ->set('email', 'muhasebe@acme.com')
            ->set('name', 'Muhasebe')
            ->set('scopeType', 'workplace')
            ->set('companyId', (string) $workplace->company_id)
            ->set('workplaceId', (string) $workplace->id)
            ->set('permissions', ['workplace.view', 'payroll.view'])
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'muhasebe@acme.com')->firstOrFail();
        $this->assertTrue($user->type->isClient());
        $this->assertTrue(Hash::check($component->get('createdPassword'), $user->password));
        $this->assertTrue($user->hasPermissionOn(Permission::WorkplaceView, $workplace));
        $this->assertFalse($user->hasPermissionOn(Permission::CompanyView, $workplace->company));
    }

    public function test_delegate_cannot_grant_permissions_they_do_not_have(): void
    {
        $manager = User::factory()->create();
        app(GrantAccess::class)->handle($manager, $this->firm, [Permission::FirmManageUsers, Permission::CompanyView]);
        $this->actingAs($manager);

        $page = Livewire::test('pages::panel.users.index');
        $this->assertSame(['firm.manage_users', 'company.view'], $page->instance()->grantable);

        $page
            ->call('newUser')
            ->set('email', 'kisi@acme.com')
            ->set('name', 'Kişi')
            ->set('permissions', ['company.view', 'workplace.view_credentials'])
            ->call('save')
            ->assertHasErrors('permissions');

        $this->assertDatabaseMissing('users', ['email' => 'kisi@acme.com']);

        // A template that exceeds the manager's permissions is rejected too.
        $template = PermissionTemplate::create(['name' => 'Geniş', 'permissions' => ['company.view', 'company.delete']]);

        Livewire::test('pages::panel.users.index')
            ->set('email', 'kisi@acme.com')
            ->set('name', 'Kişi')
            ->set('templateId', (string) $template->id)
            ->call('save')
            ->assertHasErrors('permissions');

        Livewire::test('pages::panel.users.index')
            ->set('email', 'kisi@acme.com')
            ->set('name', 'Kişi')
            ->set('permissions', ['company.view'])
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_delegate_cannot_touch_own_or_hrd_access(): void
    {
        $specialist = User::factory()->payrollSpecialist()->create(['email' => 'uzman@hrd.com']);
        $hrdGrant = app(GrantAccess::class)->handle($specialist, $this->firm, [Permission::CompanyView]);
        $ownGrant = AccessGrant::where('user_id', $this->owner->id)->firstOrFail();

        Livewire::test('pages::panel.users.index')
            ->set('email', 'uzman@hrd.com')
            ->set('permissions', ['company.view', 'company.update'])
            ->call('save')
            ->assertHasErrors('email')
            ->set('email', $this->owner->email)
            ->call('save')
            ->assertHasErrors('email')
            ->call('removeGrant', $hrdGrant->id)
            ->call('removeGrant', $ownGrant->id);

        $this->assertModelExists($hrdGrant);
        $this->assertModelExists($ownGrant);
    }

    public function test_edit_moves_grant_and_remove_revokes(): void
    {
        $company = Company::factory()->for($this->firm)->create();
        $member = User::factory()->create();
        $grant = app(GrantAccess::class)->handle($member, $this->firm, [Permission::CompanyView]);

        Livewire::test('pages::panel.users.index')
            ->call('editGrant', $grant->id)
            ->set('scopeType', 'company')
            ->set('companyId', (string) $company->id)
            ->call('save')
            ->assertHasNoErrors();

        $moved = AccessGrant::where('user_id', $member->id)->sole();
        $this->assertSame(ScopeType::Company, $moved->scope_type);

        Livewire::test('pages::panel.users.index')->call('removeGrant', $moved->id);
        $this->assertSame(0, AccessGrant::where('user_id', $member->id)->count());
    }

    public function test_users_without_manage_permission_cannot_open_the_page(): void
    {
        $viewer = User::factory()->create();
        app(GrantAccess::class)->handle($viewer, $this->firm, [Permission::CompanyView]);

        $this->actingAs($viewer)->get(route('users.index'))->assertForbidden();
    }

    public function test_grants_of_other_firms_are_not_listed_or_editable(): void
    {
        $other = Firm::factory()->create();
        $stranger = User::factory()->create(['name' => 'Yabancı Kişi']);
        $foreignGrant = app(GrantAccess::class)->handle($stranger, $other, [Permission::CompanyView]);

        Livewire::test('pages::panel.users.index')
            ->assertDontSee('Yabancı Kişi')
            ->call('removeGrant', $foreignGrant->id)
            ->assertNotFound();
    }
}
