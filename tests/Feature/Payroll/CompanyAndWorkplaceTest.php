<?php

namespace Tests\Feature\Payroll;

use App\Actions\Access\GrantAccess;
use App\Actions\Companies\SaveCompany;
use App\Actions\Workplaces\RevealWorkplaceCredential;
use App\Actions\Workplaces\SaveWorkplace;
use App\Enums\CompanyType;
use App\Enums\Permission;
use App\Enums\RegistrationType;
use App\Models\Company;
use App\Models\CredentialAccessLog;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyAndWorkplaceTest extends PayrollTestCase
{
    public function test_company_is_created_with_required_fields(): void
    {
        $firm = Firm::factory()->create();

        $company = app(SaveCompany::class)->create($firm, $this->companyInput(['website' => ' ']));

        $this->assertSame('1001', $company->company_no);
        $this->assertNull($company->website);
        $this->assertSame(1, Company::withoutWorkplaces()->count(), 'A new company still needs its first workplace.');
    }

    public function test_company_required_fields_and_formats_are_validated(): void
    {
        $firm = Firm::factory()->create();

        try {
            app(SaveCompany::class)->create($firm, [
                'tax_number' => '1234567891',
                'mersis_no' => '123',
                'company_type' => 'kooperatif',
            ]);
            $this->fail('Expected validation to fail.');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(
                ['company_no', 'title', 'short_name', 'company_type', 'sector_id', 'tax_number', 'tax_office', 'mersis_no'],
                array_keys($e->errors()),
            );
            $this->assertStringContainsString('Vergi Numarası', $e->errors()['tax_number'][0]);
        }
    }

    public function test_company_number_is_unique_across_the_system(): void
    {
        app(SaveCompany::class)->create(Firm::factory()->create(), $this->companyInput());

        $this->expectException(ValidationException::class);

        app(SaveCompany::class)->create(Firm::factory()->create(), $this->companyInput());
    }

    public function test_sole_proprietorship_may_use_tckn_as_tax_number(): void
    {
        $firm = Firm::factory()->create();
        $input = $this->companyInput(['tax_number' => '10000000146']);

        try {
            app(SaveCompany::class)->create($firm, $input);
            $this->fail('A TCKN is not a valid tax number for a joint-stock company.');
        } catch (ValidationException) {
            //
        }

        $company = app(SaveCompany::class)->create($firm, [...$input, 'company_type' => CompanyType::SoleProprietorship->value]);
        $this->assertSame('10000000146', $company->tax_number);
    }

    public function test_workplace_resolves_province_and_district_names(): void
    {
        $company = Company::factory()->create();

        $workplace = app(SaveWorkplace::class)->create($company, $this->workplaceInput());

        $this->assertSame(34, $workplace->province_id);
        $this->assertNull($workplace->province_name);
        $this->assertSame('Kadıköy', $workplace->district?->name);
        $this->assertSame(0, Company::withoutWorkplaces()->count());
    }

    public function test_unknown_district_is_kept_as_manual_entry(): void
    {
        $company = Company::factory()->create();

        $workplace = app(SaveWorkplace::class)->create($company, $this->workplaceInput(['district_name' => 'Yeni İlçe']));

        $this->assertSame(34, $workplace->province_id);
        $this->assertNull($workplace->district_id);
        $this->assertSame('Yeni İlçe', $workplace->districtLabel());
    }

    public function test_workplace_rules(): void
    {
        $company = Company::factory()->create();
        app(SaveWorkplace::class)->create($company, $this->workplaceInput());

        try {
            app(SaveWorkplace::class)->create($company, $this->workplaceInput([
                'sgk_declaration_username' => '12345678901',
                'sgk_registry_no' => '123',
                'closing_date' => '2019-01-01',
                'has_union' => true,
                'province_name' => null,
            ]));
            $this->fail('Expected validation to fail.');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(
                ['workplace_no', 'sgk_declaration_username', 'sgk_registry_no', 'closing_date', 'union_name', 'province_id', 'province_name'],
                array_keys($e->errors()),
            );
        }

        // The same workplace number is fine under another company.
        $this->assertInstanceOf(Workplace::class, app(SaveWorkplace::class)->create(Company::factory()->create(), $this->workplaceInput()));
    }

    public function test_natural_person_workplace_may_use_tckn_as_tax_number(): void
    {
        $workplace = app(SaveWorkplace::class)->create(Company::factory()->create(), $this->workplaceInput([
            'registration_type' => RegistrationType::Natural->value,
            'tax_number' => '10000000146',
        ]));

        $this->assertSame(RegistrationType::Natural, $workplace->registration_type);
    }

    public function test_credentials_are_encrypted_hidden_and_kept_when_left_blank_on_update(): void
    {
        $workplace = app(SaveWorkplace::class)->create(Company::factory()->create(), $this->workplaceInput());

        $raw = DB::table('workplaces')->where('id', $workplace->id)->value('sgk_workplace_password');
        $this->assertNotSame('isyeri-sifre', $raw);
        $this->assertStringNotContainsString('isyeri-sifre', json_encode($workplace->toArray()) ?: '');

        app(SaveWorkplace::class)->update($workplace, $this->workplaceInput([
            'branch_name' => 'Merkez Ofis',
            'sgk_workplace_password' => '',
            'sgk_system_password' => 'yeni-sifre',
        ]));

        $fresh = $workplace->fresh();
        $this->assertSame('Merkez Ofis', $fresh?->branch_name);
        $this->assertSame('isyeri-sifre', $fresh?->sgk_workplace_password);
        $this->assertSame('yeni-sifre', $fresh?->sgk_system_password);
    }

    public function test_revealing_a_credential_requires_permission_and_is_logged(): void
    {
        $workplace = app(SaveWorkplace::class)->create(Company::factory()->create(), $this->workplaceInput());
        $user = User::factory()->payrollSpecialist()->create();
        app(GrantAccess::class)->handle($user, $workplace, [Permission::WorkplaceView]);

        try {
            app(RevealWorkplaceCredential::class)->handle($workplace, 'sgk_system_password', $user);
            $this->fail('Viewing credentials needs its own permission.');
        } catch (AuthorizationException) {
            $this->assertSame(0, CredentialAccessLog::count());
        }

        app(GrantAccess::class)->handle($user, $workplace, [Permission::WorkplaceView, Permission::WorkplaceViewCredentials]);

        $value = app(RevealWorkplaceCredential::class)->handle($workplace, 'sgk_system_password', $user, '127.0.0.1');

        $this->assertSame('sistem-sifre', $value);
        $this->assertDatabaseHas('credential_access_logs', [
            'user_id' => $user->id, 'workplace_id' => $workplace->id, 'field' => 'sgk_system_password',
        ]);
    }
}
