<?php

namespace Tests\Feature\Panel;

use App\Actions\Access\GrantAccess;
use App\Enums\AuditEvent;
use App\Enums\DefinitionType;
use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Definition;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use App\Notifications\SetupApproved;
use Database\Seeders\PayrollCodeSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kurulum onayı: the payroll specialist signs off a complete setup; firm users are told, forms warn afterwards.
 */
class SetupApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private Workplace $workplace;

    private User $owner;

    private User $specialist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferenceDataSeeder::class, PayrollCodeSeeder::class]);
        $this->firm = Firm::factory()->create();
        $this->workplace = Workplace::factory()->for(Company::factory()->for($this->firm))->create();
        $this->owner = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($this->owner, $this->firm, Permission::firmOwnerDefaults());
        $this->specialist = User::factory()->payrollSpecialist()->create();
        app(GrantAccess::class)->handle($this->specialist, $this->firm, Permission::firmOwnerDefaults());
        $this->firm->forceFill(['specialist_id' => $this->specialist->id])->save();

        foreach ([DefinitionType::UpperUnit, DefinitionType::Title, DefinitionType::Position] as $type) {
            Definition::create(['firm_id' => $this->firm->id, 'type' => $type, 'code' => 'K'.$type->value, 'name' => $type->singular()]);
        }
    }

    public function test_specialist_approves_a_complete_setup_and_can_revoke(): void
    {
        $this->actingAs($this->specialist);

        // No personnel yet: the setup is not complete.
        Livewire::test('pages::panel.setup.wizard', ['step' => 5])->call('approve');
        $this->assertNull($this->firm->fresh()?->setup_approved_at);

        Employee::factory()->for($this->workplace)->create();
        $this->get(route('dashboard'))->assertSee('data-test="setup-awaiting-approval"', false);

        Livewire::test('pages::panel.setup.wizard')->set('step', 5)
            ->call('approve')
            ->assertSee('Kurulum onaylandı');

        $firm = $this->firm->fresh();
        $this->assertNotNull($firm?->setup_approved_at);
        $this->assertSame($this->specialist->id, $firm->setup_approved_by);
        $this->assertSame(1, AuditLog::where('event', AuditEvent::FirmSetupApproved)->count());
        $this->assertSame(1, $this->owner->notifications()->where('type', SetupApproved::class)->count());
        $this->assertSame(0, $this->specialist->notifications()->count(), 'The approver is not notified.');

        $this->actingAs($this->owner);
        $this->get(route('workplaces.edit', $this->workplace))->assertOk()->assertSee('data-test="setup-approved-notice"', false);
        $this->get(route('dashboard'))->assertDontSee('data-test="setup-awaiting-approval"', false);
        Livewire::test('pages::panel.setup.wizard')->set('step', 5)->assertDontSee('Onayı kaldır')->call('revokeApproval')->assertForbidden();

        $this->actingAs($this->specialist);
        Livewire::test('pages::panel.setup.wizard')->set('step', 5)->call('revokeApproval');
        $this->assertNull($this->firm->fresh()?->setup_approved_at);
    }
}
