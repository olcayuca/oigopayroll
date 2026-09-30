<?php

namespace Tests\Feature\Payroll;

use App\Actions\Companies\SaveCompany;
use App\Actions\Firms\CreateFirm;
use App\Actions\Firms\RegisterFirm;
use App\Actions\Firms\ReviewFirm;
use App\Enums\FirmSource;
use App\Enums\FirmStatus;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FirmLifecycleTest extends PayrollTestCase
{
    public function test_client_registered_firm_waits_for_approval_and_creator_gets_access(): void
    {
        $client = User::factory()->create();

        $firm = app(RegisterFirm::class)->handle($client, ['name' => 'Acme']);

        $this->assertSame(FirmStatus::Pending, $firm->status);
        $this->assertSame(FirmSource::Client, $firm->source);
        $this->assertTrue($client->can('view', $firm));
        $this->assertFalse($client->can('create', [Company::class, $firm]), 'Pending firms cannot add companies.');
    }

    public function test_company_cannot_be_created_under_pending_firm(): void
    {
        $firm = Firm::factory()->pending()->create();

        $this->expectException(ValidationException::class);

        app(SaveCompany::class)->create($firm, $this->companyInput());
    }

    public function test_super_admin_approves_firm_which_becomes_usable(): void
    {
        $client = User::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $firm = app(RegisterFirm::class)->handle($client, ['name' => 'Acme']);

        $this->assertTrue($admin->can('review', $firm));
        $this->assertFalse($client->can('review', $firm));

        app(ReviewFirm::class)->approve($firm, $admin);

        $this->assertSame(FirmStatus::Active, $firm->refresh()->status);
        $this->assertSame($admin->id, $firm->reviewed_by);
        $this->assertTrue($client->can('create', [Company::class, $firm]));
    }

    public function test_rejection_requires_a_reason_and_only_pending_firms_can_be_reviewed(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $firm = Firm::factory()->pending()->create();

        try {
            app(ReviewFirm::class)->reject($firm, $admin, '  ');
            $this->fail('Rejection without reason should fail.');
        } catch (ValidationException) {
            //
        }

        app(ReviewFirm::class)->reject($firm, $admin, 'Eksik evrak');
        $this->assertSame(FirmStatus::Rejected, $firm->refresh()->status);

        $this->expectException(ValidationException::class);
        app(ReviewFirm::class)->approve($firm, $admin);
    }

    public function test_hrd_created_firm_is_active_without_any_client_user(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $firm = app(CreateFirm::class)->handle($admin, ['name' => 'Beta Holding']);

        $this->assertSame(FirmStatus::Active, $firm->status);
        $this->assertSame(FirmSource::Hrd, $firm->source);
        $this->assertSame(0, $firm->companies()->count());
    }

    public function test_only_client_users_register_and_only_super_admin_creates_firms_directly(): void
    {
        $client = User::factory()->create();
        $specialist = User::factory()->payrollSpecialist()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue($client->can('register', Firm::class));
        $this->assertFalse($specialist->can('register', Firm::class));
        $this->assertFalse($client->can('create', Firm::class));
        $this->assertFalse($specialist->can('create', Firm::class));
        $this->assertTrue($admin->can('create', Firm::class));
    }
}
