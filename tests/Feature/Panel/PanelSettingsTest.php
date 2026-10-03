<?php

namespace Tests\Feature\Panel;

use App\Actions\Access\GrantAccess;
use App\Enums\Permission;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use App\Notifications\SetupApproved;
use App\Notifications\SupportTicketUpdate;
use App\Support\FirmSettings;
use Database\Seeders\PayrollCodeSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panel → Ayarlar (prototype): firm settings for managers, personal settings for everyone.
 */
class PanelSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private User $owner;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferenceDataSeeder::class, PayrollCodeSeeder::class]);
        $this->firm = Firm::factory()->create(['name' => 'Oigo Grup']);
        Workplace::factory()->for(Company::factory()->for($this->firm))->create();
        $this->owner = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($this->owner, $this->firm, Permission::firmOwnerDefaults());
        $this->member = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($this->member, $this->firm, [Permission::FirmView, Permission::EmployeeView]);
    }

    public function test_tabs_render_and_firm_details_are_saved(): void
    {
        $this->actingAs($this->owner);

        foreach (['sirket', 'bordro', 'bildirim', 'guvenlik', 'tercih'] as $tab) {
            $this->get(route('settings.index', ['sekme' => $tab]))->assertOk()->assertSee('Değişiklikleri Kaydet');
        }
        // The logo is saved on upload.
        $this->get(route('settings.index', ['sekme' => 'marka']))->assertOk()->assertSee('Logo yüklenmedi')->assertDontSee('Değişiklikleri Kaydet');
        $this->get(route('dashboard'))->assertSee(route('settings.index'), false);

        Livewire::test('pages::panel.settings.index')
            ->set('firmForm.mersis_no', '123')
            ->call('save')
            ->assertHasErrors(['firmForm.mersis_no'])
            ->set('firmForm.mersis_no', '0634012398700015')
            ->set('firmForm.kep_address', 'oigo@hs01.kep.tr')
            ->set('firmForm.website', 'oigo.com.tr')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['0634012398700015', 'oigo@hs01.kep.tr', 'oigo.com.tr'], [$this->firm->fresh()?->mersis_no, $this->firm->fresh()?->kep_address, $this->firm->fresh()?->website]);
    }

    public function test_payroll_defaults_prefill_new_personnel(): void
    {
        $this->actingAs($this->owner);

        Livewire::test('pages::panel.settings.index', ['tab' => 'bordro'])
            ->set('tab', 'bordro')
            ->set('payroll.wage_type', 'Net')
            ->set('payroll.auto_bes', false)
            ->set('payroll.minimum_wage_exemption', false)
            ->call('save');

        $this->assertSame('Net', FirmSettings::payroll($this->firm->fresh())['wage_type']);

        Livewire::test('pages::panel.employees.form')
            ->assertSet('form.wage_type', 'Net')
            ->assertSet('form.bes_rate', '0')
            ->assertSet('form.minimum_wage_exemption', false);
    }

    public function test_members_see_firm_settings_read_only_but_manage_their_own(): void
    {
        $this->actingAs($this->member);
        $this->get(route('settings.index'))->assertOk()->assertDontSee('Değişiklikleri Kaydet')->assertSee('yalnızca firma bilgilerini düzenleme yetkisi');
        Livewire::test('pages::panel.settings.index')->set('firmForm.phone', '123')->call('save')->assertForbidden();

        // Notification choices: support tickets switched off in the panel.
        Livewire::test('pages::panel.settings.index')
            ->set('tab', 'bildirim')
            ->set('prefs.setup.panel', false)
            ->call('save');

        $this->member->refresh();
        $this->assertFalse($this->member->wantsNotification(\App\Notifications\NotificationCategory::Setup, 'panel'));
        $this->member->notify(new SetupApproved($this->firm, $this->owner));
        $this->assertSame(0, $this->member->notifications()->count(), 'Switched off: not stored.');
        $this->assertSame(['database'], (new SupportTicketUpdate(
            \App\Models\SupportTicket::forceCreate(['firm_id' => $this->firm->id, 'subject' => 'x', 'category' => 'Öneri', 'module' => 'Diğer', 'priority' => 'normal', 'status' => 'open']),
            't', 'b'))->via($this->member));

        // Own profile.
        Livewire::test('pages::panel.settings.index')->set('tab', 'tercih')->set('name', 'Ali Yeni')->call('save')->assertHasNoErrors();
        $this->assertSame('Ali Yeni', $this->member->fresh()?->name);
    }

    public function test_firm_can_require_two_factor(): void
    {
        $this->actingAs($this->owner);
        Livewire::test('pages::panel.settings.index')->set('tab', 'guvenlik')->set('requireTwoFactor', true)->call('save');
        $this->assertTrue(FirmSettings::requiresTwoFactor($this->firm->fresh()));

        $this->actingAs($this->member);
        $this->get(route('dashboard'))->assertRedirect(route('security.edit'));

        $this->member->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_logo_upload_and_access(): void
    {
        Storage::fake('local');
        $this->actingAs($this->owner);

        Livewire::test('pages::panel.settings.index')->set('tab', 'marka')
            ->set('logo', UploadedFile::fake()->image('logo.png', 400, 120))
            ->assertHasNoErrors();

        $path = (string) $this->firm->fresh()?->logo_path;
        Storage::disk('local')->assertExists($path);
        $this->get(route('firms.logo', $this->firm))->assertOk();
        $this->get(route('dashboard'))->assertSee(route('firms.logo', $this->firm), false);

        $outsider = User::factory()->create();
        app(GrantAccess::class)->handle($outsider, Firm::factory()->create(), Permission::firmOwnerDefaults());
        $this->actingAs($outsider);
        $this->get(route('firms.logo', $this->firm))->assertForbidden();

        $this->actingAs($this->owner);
        Livewire::test('pages::panel.settings.index')->set('tab', 'marka')->call('removeLogo');
        Storage::disk('local')->assertMissing($path);
    }
}
