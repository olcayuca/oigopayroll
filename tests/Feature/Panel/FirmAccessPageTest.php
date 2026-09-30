<?php

namespace Tests\Feature\Panel;

use App\Actions\Access\GrantAccess;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Models\AccessGrant;
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

    private User $accountingBoss;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->accounting = Firm::factory()->create(['name' => 'Muhasebe A.Ş.', 'tax_number' => TurkishIdentifiers::makeVkn('555555555')]);
        $this->client = Firm::factory()->create(['name' => 'Müşteri Ltd.']);

        $this->clientOwner = User::factory()->create();
        app(GrantAccess::class)->handle($this->clientOwner, $this->client, Permission::firmOwnerDefaults());

        $this->accountingBoss = User::factory()->create(['name' => 'Muhasebe Müdürü']);
        app(GrantAccess::class)->handle($this->accountingBoss, $this->accounting, Permission::firmOwnerDefaults());

        $this->accountant = User::factory()->create(['name' => 'Muhasebeci Ayşe']);
        app(GrantAccess::class)->handle($this->accountant, $this->accounting, [Permission::CompanyView]);
    }

    public function test_full_flow_manage_my_firm_then_assign_own_users(): void
    {
        // 1. The client firm lets the accounting firm manage it.
        $this->actingAs($this->clientOwner);
        $this->get(route('firm-access.index'))->assertOk();

        Livewire::test('pages::panel.firm-access.index')
            ->call('newLink')
            ->set('taxNumber', '1111111111')
            ->call('saveLink')
            ->assertHasErrors('taxNumber')
            ->set('taxNumber', $this->accounting->tax_number)
            ->set('permissions', ['company.view', 'company.create'])
            ->call('saveLink')
            ->assertHasNoErrors()
            ->assertSee('Muhasebe A.Ş.');

        // 2. The link alone gives nobody access.
        $this->assertFalse($this->accountant->fresh()->canSee($this->client));

        // 3. The accounting firm assigns its own accountant, within the link.
        $this->actingAs($this->accountingBoss);
        $this->accountingBoss->switchFirm($this->accounting);
        $link = FirmLink::sole();

        Livewire::test('pages::panel.firm-access.index')
            ->assertSee('Müşteri Ltd.')
            ->call('newAssignment', $link->id)
            ->set('assignUserId', (string) $this->accountant->id)
            ->set('permissions', ['company.view', 'company.delete'])
            ->call('saveAssignment')
            ->assertHasErrors('permissions')
            ->set('permissions', ['company.view', 'company.create'])
            ->call('saveAssignment')
            ->assertHasNoErrors()
            ->assertSee('Muhasebeci Ayşe');

        // 4. The accountant works on the client firm.
        $this->actingAs($this->accountant);
        Livewire::test('firm-switcher')->assertSee('Muhasebe A.Ş.')->assertSee('Müşteri Ltd.');
        $this->accountant->switchFirm($this->client);
        $this->get(route('companies.create'))->assertOk();

        // 5. The client sees the accountant as a (read-only) user of the accounting firm.
        $this->actingAs($this->clientOwner);
        $page = Livewire::test('pages::panel.users.index')->assertSee('Muhasebe A.Ş. kullanıcısı');
        $foreignGrant = AccessGrant::where('user_id', $this->accountant->id)->where('scope_id', $this->client->id)->sole();
        $page->call('removeGrant', $foreignGrant->id);
        $this->assertModelExists($foreignGrant);

        // 6. Revoking the link removes the accountant's access too.
        Livewire::test('pages::panel.firm-access.index')->call('removeLink', $link->id);
        $this->assertModelMissing($foreignGrant);
        $this->assertFalse($this->accountant->fresh()->canSee($this->client));
    }

    public function test_sub_firm_created_from_the_panel(): void
    {
        $this->actingAs($this->accountingBoss);
        $this->accountingBoss->switchFirm($this->accounting);

        Livewire::test('pages::panel.firm-access.index')
            ->set('firmForm', ['name' => ''])
            ->call('createSubFirm')
            ->assertHasErrors('firmForm.name')
            ->set('firmForm', ['name' => 'Muhasebe Şube'])
            ->call('createSubFirm')
            ->assertHasNoErrors()
            ->assertSee('Muhasebe Şube')
            ->assertSee('Alt firma');

        $sub = Firm::where('name', 'Muhasebe Şube')->firstOrFail();
        $this->assertSame($this->accounting->id, $sub->parent_firm_id);
        $this->assertTrue($this->accountingBoss->fresh()->canSee($sub));
    }

    public function test_client_users_cannot_be_added_to_another_firm(): void
    {
        $this->actingAs($this->clientOwner);

        Livewire::test('pages::panel.users.index')
            ->set('email', $this->accountant->email)
            ->set('permissions', ['company.view'])
            ->call('save')
            ->assertHasErrors('email');

        $this->assertFalse($this->accountant->fresh()->canSee($this->client));
    }

    public function test_admin_links_firms_and_cannot_leak_users(): void
    {
        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test('pages::admin.firms.show', ['firm' => $this->client])
            ->call('newLink', 'manager')
            ->set('linkFirmId', (string) $this->accounting->id)
            ->set('templateId', '')
            ->set('permissions', ['company.view'])
            ->call('saveLink')
            ->assertHasNoErrors();

        $this->assertSame($this->accounting->id, FirmLink::sole()->manager_firm_id);

        // A client user of a third firm cannot be granted on the client firm, even by an admin.
        $other = User::factory()->create();
        app(GrantAccess::class)->handle($other, Firm::factory()->create(), [Permission::CompanyView]);

        Livewire::test('pages::admin.users.show', ['user' => $other])
            ->call('newGrant')
            ->set('firmId', (string) $this->client->id)
            ->set('permissions', ['company.view'])
            ->call('saveGrant')
            ->assertHasErrors('grant');

        $this->assertFalse($other->fresh()->canSee($this->client));
    }

    public function test_users_without_manage_permission_cannot_open_firm_access(): void
    {
        $this->actingAs($this->accountant)->get(route('firm-access.index'))->assertForbidden();
    }
}
