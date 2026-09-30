<?php

namespace Tests\Feature\Admin;

use App\Enums\Portal;
use App\Models\Company;
use App\Models\RiskClass;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());
    }

    public function test_page_renders_each_tab(): void
    {
        foreach (['genel', 'sectors', 'risk-classes'] as $tab) {
            $this->get(route('admin.settings.index', ['sekme' => $tab]))->assertOk();
        }
    }

    public function test_general_settings_are_saved_and_used_in_the_logo(): void
    {
        Livewire::test('pages::admin.settings.index')
            ->set('general.site_name', 'HRD Payroll')
            ->set('general.contact_email', 'gecersiz')
            ->call('saveGeneral')
            ->assertHasErrors('general.contact_email')
            ->set('general.contact_email', 'info@hrd.com.tr')
            ->call('saveGeneral')
            ->assertHasNoErrors();

        $this->assertSame('HRD Payroll', Setting::get('site.name'));
        $this->assertSame('info@hrd.com.tr', Setting::get('contact.email'));
        $this->get(route('admin.dashboard'))->assertSee('HRD Payroll');
    }

    public function test_reference_list_add_rename_toggle_and_protected_delete(): void
    {
        $component = Livewire::test('pages::admin.settings.index')
            ->set('tab', 'risk-classes')
            ->set('newItem', '1. Derece')
            ->call('addItem')
            ->assertHasNoErrors()
            ->set('newItem', '1. Derece')
            ->call('addItem')
            ->assertHasErrors('newItem');

        $risk = RiskClass::where('name', '1. Derece')->firstOrFail();

        $component->call('startEditing', $risk->id)
            ->set('editingItemName', 'Birinci Derece')
            ->call('saveItem')
            ->call('toggleItem', $risk->id);

        $risk->refresh();
        $this->assertSame('Birinci Derece', $risk->name);
        $this->assertFalse($risk->is_active);

        // Used sectors cannot be deleted; unused ones can.
        $used = Sector::create(['name' => 'Kullanılan']);
        Company::factory()->create(['sector_id' => $used->id]);
        $unused = Sector::create(['name' => 'Boşta']);

        Livewire::test('pages::admin.settings.index')
            ->set('tab', 'sectors')
            ->call('deleteItem', $used->id)
            ->call('deleteItem', $unused->id);

        $this->assertModelExists($used);
        $this->assertModelMissing($unused);
    }

    public function test_non_admins_cannot_manage_settings(): void
    {
        $this->actingAs(User::factory()->payrollSpecialist()->create());

        Livewire::test('pages::admin.settings.index')->assertForbidden();
    }
}
