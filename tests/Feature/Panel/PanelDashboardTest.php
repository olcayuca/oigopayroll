<?php

namespace Tests\Feature\Panel;

use App\Actions\Access\GrantAccess;
use App\Enums\FirmSource;
use App\Enums\FirmStatus;
use App\Enums\Permission;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_without_firm_registers_one_which_waits_for_approval(): void
    {
        $client = User::factory()->create();

        $this->actingAs($client)->get(route('dashboard'))->assertOk()->assertSee('Firmanızı oluşturun');

        Livewire::test('pages::panel.dashboard')
            ->set('firmName', 'Acme Gıda')
            ->call('registerFirm')
            ->assertHasNoErrors()
            ->assertSee('Firmanız onay bekliyor');

        $firm = Firm::where('name', 'Acme Gıda')->firstOrFail();
        $this->assertSame(FirmStatus::Pending, $firm->status);
        $this->assertSame(FirmSource::Client, $firm->source);
        $this->assertSame($firm->id, $client->refresh()->current_firm_id);
    }

    public function test_specialist_without_grants_sees_a_notice_and_cannot_register_a_firm(): void
    {
        $this->actingAs(User::factory()->payrollSpecialist()->create());

        Livewire::test('pages::panel.dashboard')
            ->assertSee('Yetkili olduğunuz firma yok')
            ->set('firmName', 'X')
            ->call('registerFirm')
            ->assertForbidden();
    }

    public function test_active_firm_is_remembered_and_can_be_switched_only_to_visible_firms(): void
    {
        $specialist = User::factory()->payrollSpecialist()->create();
        $alpha = Firm::factory()->create(['name' => 'Alfa']);
        $beta = Firm::factory()->create(['name' => 'Beta']);
        $hidden = Firm::factory()->create(['name' => 'Gizli']);
        app(GrantAccess::class)->handle($specialist, $alpha, [Permission::FirmView]);
        app(GrantAccess::class)->handle($specialist, $beta, [Permission::FirmView]);

        $this->actingAs($specialist);

        $this->assertSame($alpha->id, $specialist->activeFirm()?->id, 'Defaults to the first visible firm.');

        Livewire::test('firm-switcher')
            ->assertSee('Alfa')
            ->assertSee('Beta')
            ->assertDontSee('Gizli')
            ->call('switchTo', $beta->id)
            ->assertRedirect();

        $this->assertSame($beta->id, $specialist->refresh()->current_firm_id);

        Livewire::test('firm-switcher')->call('switchTo', $hidden->id)->assertNotFound();
        $this->assertSame($beta->id, $specialist->refresh()->current_firm_id);
    }

    public function test_lost_access_falls_back_to_another_visible_firm(): void
    {
        $client = User::factory()->create();
        $old = Firm::factory()->create(['name' => 'Eski']);
        $new = Firm::factory()->create(['name' => 'Yeni']);
        app(GrantAccess::class)->handle($client, $new, [Permission::FirmView]);
        $client->forceFill(['current_firm_id' => $old->id])->save();

        $this->assertSame($new->id, $client->activeFirm()?->id);
    }
}
