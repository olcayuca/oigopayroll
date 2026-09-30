<?php

namespace Tests\Feature\Panel;

use App\Actions\Access\GrantAccess;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Models\Company;
use App\Models\Firm;
use App\Models\FirmLink;
use App\Models\User;
use App\Support\TurkishIdentifiers;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FirmAccessPageTest extends TestCase
{
    use RefreshDatabase;

    private Firm $accounting;

    private Firm $client;

    private User $clientOwner;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->accounting = Firm::factory()->create(['name' => 'Muhasebe A.Ş.', 'tax_number' => TurkishIdentifiers::makeVkn('555555555')]);
        $this->client = Firm::factory()->create(['name' => 'Müşteri Ltd.']);

        $this->clientOwner = User::factory()->create();
        app(GrantAccess::class)->handle($this->clientOwner, $this->client, Permission::firmOwnerDefaults());

        $this->accountant = User::factory()->create();
        app(GrantAccess::class)->handle($this->accountant, $this->accounting, Permission::firmOwnerDefaults());
    }

    public function test_client_owner_lets_accounting_firm_manage_them_and_accountant_works_on_both(): void
    {
        $this->actingAs($this->clientOwner);
        $this->get(route('firm-access.index'))->assertOk();

        Livewire::test('pages::panel.firm-access.index')
            ->call('newLink')
            ->set('taxNumber', '1111111111')
            ->call('save')
            ->assertHasErrors('taxNumber')
            ->set('taxNumber', $this->accounting->tax_number)
            ->set('permissions', ['company.view', 'company.create'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Muhasebe A.Ş.');

        // The accountant now sees both firms in the switcher and can add a company to the client firm.
        $this->actingAs($this->accountant);

        Livewire::test('firm-switcher')->assertSee('Muhasebe A.Ş.')->assertSee('Müşteri Ltd.');
        $this->accountant->switchFirm($this->client);

        $this->get(route('companies.create'))->assertOk();
        $this->get(route('users.index'))->assertForbidden(); // manage_users was not passed on
        $this->assertFalse($this->accountant->fresh()->hasPermissionOn(Permission::CompanyDelete, $this->client));

        // Owner revokes; the accountant loses access.
        $this->actingAs($this->clientOwner);
        Livewire::test('pages::panel.firm-access.index')->call('remove', FirmLink::sole()->id);

        $this->assertFalse($this->accountant->fresh()->canSee($this->client));
    }

    public function test_accounting_firm_page_lists_managed_firms(): void
    {
        FirmLink::create(['manager_firm_id' => $this->accounting->id, 'managed_firm_id' => $this->client->id, 'permissions' => ['company.view']]);

        $this->actingAs($this->accountant);
        $this->accountant->switchFirm($this->accounting);

        $this->get(route('firm-access.index'))->assertOk()->assertSee('Firmamızın yönettiği firmalar')->assertSee('Müşteri Ltd.');
    }

    public function test_admin_links_firms_in_both_directions(): void
    {
        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('admin.firms.show', $this->client))->assertOk()->assertSee('Firmalar Arası Yetki');

        Livewire::test('pages::admin.firms.show', ['firm' => $this->client])
            ->call('newLink', 'manager')
            ->set('linkFirmId', (string) $this->accounting->id)
            ->set('templateId', '')
            ->set('permissions', ['company.view', 'workplace.view'])
            ->call('saveLink')
            ->assertHasNoErrors();

        $link = FirmLink::sole();
        $this->assertSame($this->accounting->id, $link->manager_firm_id);
        $this->assertSame($this->client->id, $link->managed_firm_id);

        Livewire::test('pages::admin.firms.show', ['firm' => $this->accounting])
            ->assertSee('Müşteri Ltd.')
            ->call('editLink', $link->id)
            ->assertSet('linkDirection', 'managed')
            ->set('permissions', ['company.view'])
            ->call('saveLink')
            ->call('removeLink', $link->id);

        $this->assertSame(0, FirmLink::count());
    }

    public function test_users_without_manage_permission_cannot_open_firm_access(): void
    {
        $viewer = User::factory()->create();
        app(GrantAccess::class)->handle($viewer, $this->client, [Permission::CompanyView]);

        $this->actingAs($viewer)->get(route('firm-access.index'))->assertForbidden();
    }
}
