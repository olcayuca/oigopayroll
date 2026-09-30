<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditEvent;
use App\Enums\Portal;
use App\Models\AuditLog;
use App\Models\LegalParameter;
use App\Models\User;
use App\Payroll\Parameters\LegalParameters;
use App\Payroll\Parameters\ParameterCatalog;
use Database\Seeders\LegalParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class LegalParametersTest extends TestCase
{
    use RefreshDatabase;

    private function params(): LegalParameters
    {
        return new LegalParameters;
    }

    public function test_seeded_2026_values_cover_every_parameter(): void
    {
        $this->seed(LegalParameterSeeder::class);

        foreach (ParameterCatalog::all() as $definition) {
            $this->assertNotNull($this->params()->value($definition->key, '2026-06-15'), "{$definition->key} missing for 2026");
        }

        $this->assertSame('33030.00', $this->params()->value('min_wage_gross', '2026-03-01'));
        $this->assertSame('297270.00', $this->params()->value('sgk_ceiling_monthly', '2026-03-01'));
        $this->assertSame('12.0000', $this->params()->value('sgk_employer_pension', '2026-03-01'));
    }

    public function test_value_follows_the_effective_date(): void
    {
        $this->seed(LegalParameterSeeder::class);

        $this->assertSame('64948.77', $this->params()->value('severance_ceiling', '2026-06-30'));
        $this->assertSame('73729.87', $this->params()->value('severance_ceiling', '2026-07-01'));
        $this->assertNull($this->params()->value('severance_ceiling', '2025-12-31'));

        $this->expectException(InvalidArgumentException::class);
        $this->params()->require('severance_ceiling', '2025-12-31');
    }

    public function test_rates_keep_their_precision(): void
    {
        $this->params()->set('stamp_tax_rate', '2026-01-01', '0.759');

        $this->assertSame('0.7590', $this->params()->value('stamp_tax_rate', '2026-02-01'));
        $this->assertSame('%0,759', ParameterCatalog::find('stamp_tax_rate')->format('0.7590'));
    }

    public function test_bracket_rules(): void
    {
        foreach ([
            [['up_to' => '400000', 'rate' => '15'], ['up_to' => '190000', 'rate' => '20'], ['up_to' => null, 'rate' => '40']], // not ascending
            [['up_to' => '190000', 'rate' => '15'], ['up_to' => '400000', 'rate' => '20']],                                    // last not open-ended
            [['up_to' => '190000', 'rate' => '150'], ['up_to' => null, 'rate' => '40']],                                        // rate > 100
            [],
        ] as $invalid) {
            try {
                $this->params()->set('income_tax_brackets', '2026-01-01', $invalid);
                $this->fail('Invalid brackets were accepted: '.json_encode($invalid));
            } catch (ValidationException) {
                //
            }
        }

        $this->params()->set('income_tax_brackets', '2026-01-01', [['up_to' => '190000', 'rate' => '15'], ['up_to' => '', 'rate' => '40']]);
        $this->assertSame([['up_to' => '190000.00', 'rate' => '15.0000'], ['up_to' => null, 'rate' => '40.0000']], $this->params()->value('income_tax_brackets', '2026-05-01'));
    }

    public function test_seeder_never_overwrites_admin_edits(): void
    {
        $this->params()->set('meal_exemption_daily', '2026-01-01', '310');
        $this->seed(LegalParameterSeeder::class);

        $this->assertSame('310.00', $this->params()->value('meal_exemption_daily', '2026-05-01'));
    }

    public function test_admin_page_adds_a_new_period_and_logs_it(): void
    {
        $this->seed(LegalParameterSeeder::class);
        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('admin.parameters.index'))->assertOk()->assertSee('33.030,00 TL')->assertSee('role="tablist"', false);
        $this->get(route('admin.parameters.index', ['sekme' => 'vergi']))->assertOk()->assertSee('%0,759');
        $this->get(route('admin.parameters.index', ['tarih' => '2025-06-01']))->assertOk()->assertSee('tanımsız parametreler');

        Livewire::test('pages::admin.parameters.index')
            ->call('open', 'min_wage_gross')
            ->set('effectiveFrom', '2026-07-01')
            ->set('value', '-5')
            ->call('save')
            ->assertHasErrors('value')
            ->set('value', '36000')
            ->set('source', 'Test ara zam')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('33030.00', $this->params()->value('min_wage_gross', '2026-06-30'));
        $this->assertSame('36000.00', $this->params()->value('min_wage_gross', '2026-07-15'));
        $this->assertSame(1, AuditLog::where('event', AuditEvent::ParameterChanged)->count());

        $entry = LegalParameter::where('key', 'min_wage_gross')->whereDate('effective_from', '2026-07-01')->sole();
        Livewire::test('pages::admin.parameters.index')->call('open', 'min_wage_gross')->call('deleteEntry', $entry->id);
        $this->assertSame('33030.00', $this->params()->value('min_wage_gross', '2026-07-15'));
    }

    public function test_non_admins_cannot_manage_parameters(): void
    {
        $this->actingAs(User::factory()->payrollSpecialist()->create());

        Livewire::test('pages::admin.parameters.index')->assertForbidden();
    }
}
