<?php

namespace Tests\Feature\Admin;

use App\Actions\Firms\ManageContracts;
use App\Enums\AuditEvent;
use App\Enums\ContractStatus;
use App\Enums\Portal;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\FirmContract;
use App\Models\FirmDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ContractsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function contract(Firm $firm, array $overrides = []): FirmContract
    {
        return app(ManageContracts::class)->save(null, [
            'firm_id' => $firm->id, 'contract_no' => 'S-'.uniqid(), 'title' => 'Bordro Hizmet Sözleşmesi',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'auto_renew' => false, 'notice_days' => 30,
            'fee_type' => 'aylik_calisan_basi', 'fee_amount' => '250.50', 'currency' => 'TRY', ...$overrides,
        ]);
    }

    public function test_status_follows_the_dates(): void
    {
        $this->travelTo('2026-06-01');
        $firm = Firm::factory()->create();

        $this->assertSame(ContractStatus::Active, $this->contract($firm)->status());
        $this->assertSame(ContractStatus::Ending, $this->contract($firm, ['ends_on' => '2026-07-15'])->status());
        $this->assertSame(ContractStatus::Expired, $this->contract($firm, ['starts_on' => '2025-01-01', 'ends_on' => '2025-12-31'])->status());
        $this->assertSame(ContractStatus::Upcoming, $this->contract($firm, ['starts_on' => '2026-09-01', 'ends_on' => null])->status());

        // Ending soon = within notice period (30) + 30 days, computed in the database too.
        $this->assertSame(1, FirmContract::endingSoon()->count());
        $this->assertSame(2, FirmContract::running()->count());
    }

    public function test_validation_and_termination(): void
    {
        $firm = Firm::factory()->create();
        $other = Firm::factory()->create();
        $foreignDocument = FirmDocument::create([
            'firm_id' => $other->id, 'type' => 'sozlesme', 'title' => 'X', 'disk' => 'local', 'path' => 'x.pdf',
            'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size' => 1,
        ]);

        try {
            $this->contract($firm, ['ends_on' => null, 'auto_renew' => true, 'document_id' => $foreignDocument->id]);
            $this->fail('Expected validation errors.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ends_on', $e->errors());
            $this->assertArrayHasKey('document_id', $e->errors());
        }

        $contract = $this->contract($firm, ['auto_renew' => true]);
        app(ManageContracts::class)->terminate($contract, '2026-03-31', 'Müşteri talebi');

        $this->assertSame(ContractStatus::Terminated, $contract->fresh()?->status());
        $this->assertFalse($contract->fresh()?->auto_renew);
        $this->assertTrue(AuditLog::where('event', AuditEvent::ContractTerminated)->exists());
    }

    public function test_auto_renewal_extends_by_whole_terms(): void
    {
        $firm = Firm::factory()->create();
        $yearly = $this->contract($firm, ['auto_renew' => true]);                                         // 2026-01-01 – 2026-12-31
        $monthly = $this->contract($firm, ['auto_renew' => true, 'starts_on' => '2026-01-15', 'ends_on' => '2026-02-14']);
        $manual = $this->contract($firm);

        $this->travelTo('2027-03-01');
        $this->assertSame(2, app(ManageContracts::class)->renewExpired());

        $this->assertSame('2027-12-31', $yearly->fresh()?->ends_on?->toDateString());
        $this->assertSame('2027-03-14', $monthly->fresh()?->ends_on?->toDateString());
        $this->assertSame('2026-12-31', $manual->fresh()?->ends_on?->toDateString());
        $this->assertSame(2, AuditLog::where('event', AuditEvent::ContractRenewed)->count());
    }

    public function test_admin_page_and_firm_tab(): void
    {
        $this->travelTo('2026-06-01');
        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());
        $firm = Firm::factory()->create(['name' => 'Sözleşmeli Firma']);

        $this->get(route('admin.contracts.index'))->assertOk()->assertSee('role="tablist"', false);

        Livewire::test('pages::admin.contracts.index')
            ->call('create')
            ->set('form.firm_id', (string) $firm->id)
            ->set('form.contract_no', 'HRD-2026-001')
            ->set('form.ends_on', '')
            ->set('form.auto_renew', true)
            ->call('save')
            ->assertHasErrors('form.ends_on')
            ->assertSet('formTab', 'genel')
            ->set('form.ends_on', '2027-05-31')
            ->set('form.fee_amount', 'abc')
            ->call('save')
            ->assertSet('formTab', 'ucret')
            ->set('form.fee_amount', '300')
            ->call('save')
            ->assertHasNoErrors();

        $contract = FirmContract::sole();
        $this->assertSame('300.00', $contract->fee_amount);

        $this->get(route('admin.firms.show', ['firm' => $firm, 'sekme' => 'sozlesmeler']))->assertOk()->assertSee('HRD-2026-001');
        $this->get(route('admin.contracts.index', ['firma' => $firm->id, 'duzenle' => $contract->id]))->assertOk()->assertSee('Sözleşmeyi Düzenle');

        Livewire::test('pages::admin.contracts.index')
            ->call('openTerminate', $contract->id)
            ->set('terminationReason', '')
            ->call('terminate')
            ->assertHasErrors('terminationReason')
            ->set('terminationReason', 'Hizmet sona erdi')
            ->call('terminate')
            ->assertHasNoErrors();

        $this->assertNotNull($contract->fresh()?->terminated_on);
    }
}
