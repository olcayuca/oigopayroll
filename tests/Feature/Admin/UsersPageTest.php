<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Portal;
use App\Enums\ScopeType;
use App\Enums\UserType;
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

class UsersPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->onPortal(Portal::Admin);
        $this->actingAs($this->admin = User::factory()->superAdmin()->create(['name' => 'Ana Admin']));
    }

    public function test_pages_render(): void
    {
        $user = User::factory()->payrollSpecialist()->create(['name' => 'Uzman Ayşe']);

        $this->get(route('admin.users.index'))->assertOk()->assertSee('Uzman Ayşe');
        $this->get(route('admin.users.show', $user))->assertOk()->assertSee('Yetkiler');
        $this->get(route('admin.permission-templates.index'))->assertOk();
    }

    public function test_create_user_with_temporary_password(): void
    {
        $component = Livewire::test('pages::admin.users.index')
            ->set('name', 'Yeni Uzman')
            ->set('email', 'Uzman@Example.com')
            ->set('newType', 'payroll_specialist')
            ->call('createUser')
            ->assertHasNoErrors();

        $user = User::where('email', 'uzman@example.com')->firstOrFail();
        $this->assertSame(UserType::PayrollSpecialist, $user->type);
        $this->assertTrue(Hash::check($component->get('createdPassword'), $user->password));
        $component->assertSee($component->get('createdPassword'));
    }

    public function test_filters(): void
    {
        User::factory()->payrollSpecialist()->create(['name' => 'Uzman Can']);
        User::factory()->create(['name' => 'Müşteri Deniz']);

        Livewire::test('pages::admin.users.index')
            ->set('type', 'client_user')
            ->assertSee('Müşteri Deniz')
            ->assertDontSee('Uzman Can');
    }

    public function test_edit_deactivate_and_reset_password(): void
    {
        $user = User::factory()->payrollSpecialist()->create();

        $component = Livewire::test('pages::admin.users.show', ['user' => $user])
            ->set('name', 'Değişen Ad')
            ->set('type', 'client_user')
            ->call('saveProfile')
            ->assertHasNoErrors()
            ->call('toggleActive')
            ->call('resetPassword');

        $user->refresh();
        $this->assertSame('Değişen Ad', $user->name);
        $this->assertSame(UserType::ClientUser, $user->type);
        $this->assertFalse($user->is_active);
        $this->assertTrue(Hash::check($component->get('newPassword'), $user->password));
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        Livewire::test('pages::admin.users.show', ['user' => $this->admin])
            ->call('toggleActive')
            ->assertHasErrors('user');

        Livewire::test('pages::admin.users.show', ['user' => $this->admin])
            ->set('type', 'payroll_specialist')
            ->call('saveProfile')
            ->assertHasErrors('type');

        $this->assertTrue($this->admin->refresh()->isSuperAdmin());
    }

    public function test_grants_can_be_added_at_each_level_edited_and_removed(): void
    {
        $user = User::factory()->payrollSpecialist()->create();
        $workplace = Workplace::factory()->create();
        $company = $workplace->company;

        Livewire::test('pages::admin.users.show', ['user' => $user])
            ->call('newGrant')
            ->set('scopeType', 'workplace')
            ->set('firmId', (string) $company->firm_id)
            ->set('companyId', (string) $company->id)
            ->call('saveGrant')
            ->assertHasErrors('workplaceId')
            ->set('workplaceId', (string) $workplace->id)
            ->set('permissions', [Permission::WorkplaceView->value])
            ->call('saveGrant')
            ->assertHasNoErrors();

        $grant = AccessGrant::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(ScopeType::Workplace, $grant->scope_type);
        $this->assertTrue($user->fresh()->hasPermissionOn(Permission::WorkplaceView, $workplace));

        // Move the grant up to company level with a template.
        $template = PermissionTemplate::create(['name' => 'Görüntüleme', 'permissions' => ['company.view', 'workplace.view']]);

        Livewire::test('pages::admin.users.show', ['user' => $user])
            ->call('editGrant', $grant->id)
            ->assertSet('workplaceId', (string) $workplace->id)
            ->set('scopeType', 'company')
            ->set('templateId', (string) $template->id)
            ->call('saveGrant')
            ->assertHasNoErrors();

        $this->assertSame(1, AccessGrant::where('user_id', $user->id)->count());
        $this->assertTrue($user->fresh()->hasPermissionOn(Permission::CompanyView, $company));

        Livewire::test('pages::admin.users.show', ['user' => $user])
            ->call('removeGrant', AccessGrant::where('user_id', $user->id)->value('id'));

        $this->assertSame(0, AccessGrant::where('user_id', $user->id)->count());
    }

    public function test_permission_templates_crud(): void
    {
        Livewire::test('pages::admin.permission-templates.index')
            ->call('create')
            ->set('name', 'Muhasebe')
            ->call('save')
            ->assertHasErrors(['permissions' => 'required'])
            ->set('permissions', ['payroll.view', 'payroll.download'])
            ->call('save')
            ->assertHasNoErrors();

        $template = PermissionTemplate::where('name', 'Muhasebe')->firstOrFail();
        $this->assertSame(['payroll.view', 'payroll.download'], $template->permissions);

        Livewire::test('pages::admin.permission-templates.index')->call('delete', $template->id);
        $this->assertModelMissing($template);
    }

    public function test_firm_page_adds_new_and_existing_users_and_manages_status(): void
    {
        $firm = Firm::factory()->create();
        $existing = User::factory()->payrollSpecialist()->create(['email' => 'uzman@example.com']);

        $component = Livewire::test('pages::admin.firms.show', ['firm' => $firm])
            ->set('userEmail', 'yeni@musteri.com')
            ->set('userName', 'Yeni Müşteri')
            ->set('templateId', '')
            ->set('permissions', ['company.view'])
            ->call('addUser')
            ->assertHasNoErrors();

        $client = User::where('email', 'yeni@musteri.com')->firstOrFail();
        $this->assertSame(UserType::ClientUser, $client->type);
        $this->assertTrue(Hash::check($component->get('createdPassword'), $client->password));
        $this->assertTrue($client->hasPermissionOn(Permission::CompanyView, $firm));

        Livewire::test('pages::admin.firms.show', ['firm' => $firm])
            ->set('userEmail', 'uzman@example.com')
            ->set('permissions', ['company.view'])
            ->call('addUser')
            ->assertHasNoErrors()
            ->assertSet('createdPassword', null);
        $this->assertTrue($existing->hasPermissionOn(Permission::CompanyView, $firm));

        Livewire::test('pages::admin.firms.show', ['firm' => $firm])
            ->set('userEmail', 'kimse@example.com')
            ->set('permissions', ['company.view'])
            ->call('addUser')
            ->assertHasErrors('name');

        Livewire::test('pages::admin.firms.show', ['firm' => $firm])
            ->set('name', 'Yeni Ad')->call('rename')
            ->call('deactivate');
        $this->assertSame('Yeni Ad', $firm->refresh()->name);
        $this->assertSame('passive', $firm->status->value);
        $this->assertFalse($client->fresh()->can('create', [Company::class, $firm]));

        Livewire::test('pages::admin.firms.show', ['firm' => $firm])->call('reactivate');
        $this->assertSame('active', $firm->refresh()->status->value);
    }
}
