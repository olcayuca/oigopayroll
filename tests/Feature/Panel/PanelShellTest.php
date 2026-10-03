<?php

namespace Tests\Feature\Panel;

use App\Actions\Access\GrantAccess;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panel design shell (docs/ARAYUZ_KURALLARI.md §3): layout, top bar search, setup completion.
 */
class PanelShellTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->firm = Firm::factory()->create(['name' => 'Oigo Grup']);
        $this->company = Company::factory()->for($this->firm)->create(['title' => 'Oigo Yazılım A.Ş.', 'short_name' => 'Oigo Yazılım A.Ş.']);
        $this->owner = User::factory()->create(['name' => 'Selin Korkmaz']);
        app(GrantAccess::class)->handle($this->owner, $this->firm, Permission::firmOwnerDefaults());
        $this->actingAs($this->owner);
    }

    public function test_panel_pages_use_the_panel_shell_and_admin_keeps_its_own(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('class="oigo"', false)
            ->assertSee('KURULUM')
            ->assertSee('Gösterge Paneli')
            ->assertSee('Oigo Grup')
            ->assertSee('Selin Korkmaz');

        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('class="oigo"', false);
    }

    public function test_coming_soon_modules_are_not_links(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertSee('Bordro Dönemleri')->assertSee('YAKINDA');
    }

    public function test_setup_completion_follows_the_customer_setup_file(): void
    {
        $this->assertCount(36, array_merge(...array_values(Workplace::SETUP_FIELDS)), 'Firma Bilgileri sheet has 36 columns.');

        $empty = Workplace::factory()->for($this->company)->incompleteSetup()->create();
        $complete = Workplace::factory()->for($this->company)->create(['province_name' => null, 'province_id' => 34]);

        $this->assertCount(19, $empty->missingSetupFields());
        $this->assertSame(47, $empty->setupPercent());
        $this->assertSame([], $complete->fresh()->missingSetupFields(), 'İl given as id or as text both count.');
        $this->assertSame(100, $complete->fresh()->setupPercent());
        $this->assertSame([$empty->id], Workplace::query()->incompleteSetup()->pluck('id')->all());

        $list = Livewire::test('pages::panel.workplaces.index')->set('status', 'eksik');
        $this->assertSame([$empty->id], $list->instance()->workplaces->pluck('id')->all());
        $this->assertSame(['total' => 2, 'headquarters' => 2, 'complete' => 1, 'incomplete' => 1], $list->instance()->stats);
    }

    public function test_top_bar_search_finds_only_visible_records_of_the_active_firm(): void
    {
        Workplace::factory()->for($this->company)->create(['branch_name' => 'Ankara Şube']);
        $other = Company::factory()->create(['title' => 'Oigo Rakip A.Ş.', 'short_name' => 'Oigo Rakip']);
        Workplace::factory()->for($other)->create(['branch_name' => 'Ankara Gizli']);

        Livewire::test('panel-search')
            ->set('query', 'a')
            ->assertDontSee('Ankara Şube')
            ->set('query', 'Ankara')
            ->assertSee('Ankara Şube')
            ->assertDontSee('Ankara Gizli')
            ->set('query', 'Oigo')
            ->assertSee('Oigo Yazılım A.Ş.')
            ->assertDontSee('Oigo Rakip');
    }

    public function test_company_list_filters_companies_without_workplaces(): void
    {
        $withWorkplace = Company::factory()->for($this->firm)->create(['title' => 'Dolu A.Ş.']);
        Workplace::factory()->for($withWorkplace)->create();

        Livewire::test('pages::panel.companies.index')
            ->set('status', 'isyeri-yok')
            ->assertSee('Oigo Yazılım A.Ş.')
            ->assertDontSee('Dolu A.Ş.')
            ->set('status', 'isyeri-var')
            ->assertSee('Dolu A.Ş.')
            ->assertDontSee('Oigo Yazılım A.Ş.');
    }

    public function test_bell_marks_all_notifications_read(): void
    {
        $this->owner->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => ['title' => 'Belge süresi doluyor', 'level' => 'warning'],
        ]);

        Livewire::test('notification-bell', ['variant' => 'topbar'])
            ->assertSee('Belge süresi doluyor')
            ->assertSee('Tümünü okundu say')
            ->call('markAllRead')
            ->assertDontSee('Tümünü okundu say');

        $this->assertSame(0, $this->owner->unreadNotifications()->count());
    }
}
