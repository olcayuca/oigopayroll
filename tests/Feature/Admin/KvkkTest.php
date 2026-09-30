<?php

namespace Tests\Feature\Admin;

use App\Actions\Access\GrantAccess;
use App\Enums\AuditEvent;
use App\Enums\KvkkRequestStatus;
use App\Enums\Permission;
use App\Enums\PolicyType;
use App\Enums\Portal;
use App\Kvkk\AnonymizeUser;
use App\Kvkk\Policies;
use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\Firm;
use App\Models\KvkkRequest;
use App\Models\PolicyDocument;
use App\Models\User;
use Database\Seeders\KvkkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class KvkkTest extends TestCase
{
    use RefreshDatabase;

    private function decideAll(User $user, bool $explicitConsent = true): void
    {
        $policies = app(Policies::class);

        foreach ($policies->currentDocuments() as $document) {
            $policies->record($user, $document, $document->type->isMandatory() || $explicitConsent);
        }
    }

    public function test_seeder_publishes_placeholder_texts_once(): void
    {
        $this->seed(KvkkSeeder::class);
        $this->seed(KvkkSeeder::class);

        $this->assertSame(2, PolicyDocument::count());
        $this->assertStringContainsString('TASLAK', (string) app(Policies::class)->current(PolicyType::Disclosure)?->body);
    }

    public function test_without_published_texts_nobody_is_stopped(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_everyone_must_decide_before_using_either_portal(): void
    {
        $this->seed(KvkkSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('profile.edit'))->assertRedirect(route('kvkk.consent'));
        $this->get(route('kvkk.consent'))->assertOk()->assertSee('role="tablist"', false)->assertSee('Okudum, anladım');

        $disclosure = app(Policies::class)->current(PolicyType::Disclosure);
        $explicit = app(Policies::class)->current(PolicyType::ExplicitConsent);

        Livewire::test('pages::kvkk.consent')
            ->call('decide', $disclosure?->id, true)
            ->assertSet('tab', PolicyType::ExplicitConsent->value)
            ->call('decide', $explicit?->id, false) // refusing explicit consent is allowed
            ->assertRedirect(route('profile.edit')); // back to where the user was going

        $this->get(route('profile.edit'))->assertOk();
        $this->assertFalse(Consent::where('policy_document_id', $explicit?->id)->sole()->accepted);
        $this->assertTrue(AuditLog::where('event', AuditEvent::ConsentRefused)->exists());

        // Admin portal too.
        $this->onPortal(Portal::Admin);
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('kvkk.consent'));
        $this->decideAll($admin);
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_disclosure_cannot_be_refused_or_revoked(): void
    {
        $this->seed(KvkkSeeder::class);
        $user = User::factory()->create();
        $disclosure = app(Policies::class)->current(PolicyType::Disclosure);
        $this->assertNotNull($disclosure);

        $this->expectException(ValidationException::class);
        app(Policies::class)->record($user, $disclosure, false);
    }

    public function test_new_version_asks_everyone_again(): void
    {
        $this->seed(KvkkSeeder::class);
        $user = User::factory()->create();
        $this->decideAll($user);
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();

        $v2 = app(Policies::class)->publish(PolicyType::Disclosure, 'Aydınlatma', 'Yeni metin');

        $this->assertSame(2, $v2->version);
        $this->get(route('profile.edit'))->assertRedirect(route('kvkk.consent'));
        $this->assertSame([PolicyType::Disclosure->value], app(Policies::class)->pendingFor($user)->keys()->all());
    }

    public function test_user_manages_explicit_consent_and_submits_requests(): void
    {
        $this->seed(KvkkSeeder::class);
        $user = User::factory()->create();
        $this->decideAll($user, explicitConsent: false);
        $this->actingAs($user);
        $explicit = app(Policies::class)->current(PolicyType::ExplicitConsent);

        $this->get(route('kvkk.edit'))->assertOk()->assertSee('role="tablist"', false)->assertSee('Açık rıza ver');

        Livewire::test('pages::settings.kvkk')
            ->call('setConsent', $explicit?->id, true)
            ->call('setConsent', $explicit?->id, false)
            ->set('type', 'kopya')
            ->set('message', 'Hakkımdaki verilerin kopyasını istiyorum.')
            ->call('submit')
            ->assertHasNoErrors()
            ->set('type', 'silme')
            ->set('message', 'kısa')
            ->call('submit')
            ->assertHasErrors('message');

        $consent = Consent::where('policy_document_id', $explicit?->id)->sole();
        $this->assertTrue($consent->accepted);
        $this->assertNotNull($consent->revoked_at);
        $this->assertFalse($consent->isGiven());

        $request = KvkkRequest::sole();
        $this->assertSame(KvkkRequestStatus::Open, $request->status);
        $this->assertTrue($request->due_at->isSameDay(today()->addDays(30)));
        $this->assertSame($user->email, $request->requester_email);
    }

    public function test_public_document_page(): void
    {
        $this->get(route('kvkk.document', 'aydinlatma'))->assertNotFound();

        $this->seed(KvkkSeeder::class);

        $this->get(route('kvkk.document', 'aydinlatma'))->assertOk()->assertSee('Veri Sorumlusu');
    }

    public function test_admin_publishes_versions_and_handles_requests(): void
    {
        $this->seed(KvkkSeeder::class);
        $this->onPortal(Portal::Admin);
        $admin = User::factory()->superAdmin()->create();
        $this->decideAll($admin);
        $this->actingAs($admin);

        $applicant = User::factory()->create(['email' => 'basvuran@example.com']);
        app(GrantAccess::class)->handle($applicant, Firm::factory()->create(), Permission::firmOwnerDefaults());
        $this->decideAll($applicant);
        $request = KvkkRequest::create([
            'user_id' => $applicant->id, 'requester_name' => $applicant->name, 'requester_email' => $applicant->email,
            'type' => 'silme', 'status' => 'acik', 'message' => 'Verilerimi silin lütfen.', 'due_at' => today()->subDay(),
        ]);

        foreach (['metinler', 'onaylar', 'basvurular'] as $tab) {
            $this->get(route('admin.kvkk.index', ['sekme' => $tab]))->assertOk()->assertSee('role="tablist"', false);
        }
        $this->get(route('admin.kvkk.index', ['sekme' => 'basvurular']))->assertSee('gecikti');

        Livewire::test('pages::admin.kvkk.index')
            ->call('newVersion', 'acik_riza')
            ->assertSet('title', 'Açık Rıza Metni (TASLAK)')
            ->set('body', "## Yeni\n\n<script>alert(1)</script> Metin")
            ->call('publish')
            ->assertHasNoErrors()
            ->call('openRequest', $request->id)
            ->set('status', 'tamamlandi')
            ->set('response', '')
            ->call('saveRequest')
            ->assertHasErrors('response')
            ->call('anonymize')
            ->set('response', 'Hesabınız anonimleştirildi.')
            ->call('saveRequest')
            ->assertHasNoErrors();

        $v2 = app(Policies::class)->current(PolicyType::ExplicitConsent);
        $this->assertSame(2, $v2?->version);
        $this->assertStringNotContainsString('<script', (string) $v2?->html());

        $request->refresh();
        $this->assertSame(KvkkRequestStatus::Completed, $request->status);
        $this->assertNotNull($request->resolved_at);
        $this->assertSame($admin->id, $request->handled_by);

        $applicant->refresh();
        $this->assertSame("anonim-{$applicant->id}@anonim.invalid", $applicant->email);
        $this->assertFalse($applicant->is_active);
        $this->assertSame(0, AccessGrant::where('user_id', $applicant->id)->count());
        $this->assertTrue(AuditLog::where('event', AuditEvent::UserAnonymized)->exists());

        // Super admins and oneself are protected.
        $this->expectException(ValidationException::class);
        app(AnonymizeUser::class)->handle($admin, $admin);
    }

    public function test_personal_data_export(): void
    {
        $this->seed(KvkkSeeder::class);
        $this->onPortal(Portal::Admin);
        $admin = User::factory()->superAdmin()->create();
        $this->decideAll($admin);

        $applicant = User::factory()->create(['email' => 'kopya@example.com']);
        $this->decideAll($applicant);
        $request = KvkkRequest::create([
            'user_id' => $applicant->id, 'requester_name' => $applicant->name, 'requester_email' => $applicant->email,
            'type' => 'kopya', 'status' => 'acik', 'message' => 'Verilerimin kopyası.', 'due_at' => today()->addDays(30),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.kvkk.export', $request))->assertOk();

        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $data = $response->json();
        $this->assertSame('kopya@example.com', $data['kullanici']['e_posta']);
        $this->assertCount(2, $data['kvkk_onaylari']);
        $this->assertStringNotContainsString($applicant->password, (string) $response->getContent());
        $this->assertTrue(AuditLog::where('event', AuditEvent::PersonalDataExported)->exists());

        // Client users are turned away from the admin portal.
        $this->actingAs($applicant)->get(route('admin.kvkk.export', $request))->assertRedirect();
        $this->assertSame(1, AuditLog::where('event', AuditEvent::PersonalDataExported)->count());
    }
}
