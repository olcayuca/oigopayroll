<?php

namespace Tests\Feature;

use App\Actions\Access\GrantAccess;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Models\Company;
use App\Models\District;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * docs/ARAYUZ_KURALLARI.md §1: detail and multi-section form pages split their groups into tabs.
 */
class TabsRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_detail_and_form_pages_use_tabs(): void
    {
        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());
        $firm = Firm::factory()->create();
        $user = User::factory()->payrollSpecialist()->create();

        foreach ([
            route('admin.firms.show', $firm),
            route('admin.users.show', $user),
            route('admin.settings.index'),
            route('admin.website.index'),
        ] as $url) {
            $this->get($url)->assertOk()->assertSee('role="tablist"', false);
        }
    }

    public function test_panel_detail_and_form_pages_use_tabs(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $firm = Firm::factory()->create();
        $owner = User::factory()->create();
        app(GrantAccess::class)->handle($owner, $firm, Permission::firmOwnerDefaults());
        $workplace = Workplace::factory()->create(['company_id' => Company::factory()->for($firm)]);
        $this->actingAs($owner);

        foreach ([
            route('companies.show', $workplace->company),
            route('companies.create'),
            route('companies.edit', $workplace->company),
            route('workplaces.show', $workplace),
            route('workplaces.create'),
            route('workplaces.edit', $workplace),
            route('firm-access.index'),
        ] as $url) {
            $this->get($url)->assertOk()->assertSee('role="tablist"', false);
        }
    }

    public function test_form_errors_open_the_first_failing_tab(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        District::create(['province_id' => 34, 'name' => 'Kadıköy']);
        $firm = Firm::factory()->create();
        $owner = User::factory()->create();
        app(GrantAccess::class)->handle($owner, $firm, Permission::firmOwnerDefaults());
        $company = Company::factory()->for($firm)->create();
        $this->actingAs($owner);

        // Only the address tab is incomplete: saving jumps there and flags it.
        $page = Livewire::test('pages::panel.workplaces.form', ['companyId' => (string) $company->id])
            ->set('form.workplace_no', '1')->set('form.branch_name', 'Merkez')->set('form.workplace_type', 'merkez')
            ->set('form.workplace_kind', 'normal')->set('form.title', 'X')->set('form.tax_number', $company->tax_number)
            ->set('form.tax_office', 'Kadıköy')->set('form.hazard_class', 'az_tehlikeli')
            ->set('form.sgk_officer_name', 'A')->set('form.sgk_workplace_code', '1')->set('form.ebildirge_officer_name', 'A')
            ->set('form.opening_date', '2021-01-01')->set('form.sgk_declaration_username', '10000000146')
            ->set('form.sgk_workplace_password', 'a')->set('form.sgk_system_password', 'b')
            ->call('save')
            ->assertSet('tab', 'adres');

        $this->assertSame(['adres'], $page->instance()->invalidTabs());
    }
}
