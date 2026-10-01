<?php

namespace Tests\Feature\Admin;

use App\Actions\Access\GrantAccess;
use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\User;
use App\Support\Impersonation;
use Database\Seeders\KvkkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create(['name' => 'Destek Admin']);
        $this->client = User::factory()->create(['name' => 'Müşteri Ayşe']);
        app(GrantAccess::class)->handle($this->client, Firm::factory()->create(), Permission::firmOwnerDefaults());
    }

    public function test_full_flow_with_banner_audit_and_end(): void
    {
        $token = Impersonation::issue($this->admin, $this->client);

        $this->get(route('impersonation.start', $token))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->client);

        $this->get(route('dashboard'))->assertOk()->assertSee('data-test="impersonation-banner"', false)
            ->assertSee('Müşteri Ayşe')->assertSee('Destek Admin');

        // Single use.
        $this->get(route('impersonation.start', $token))->assertForbidden();

        $started = AuditLog::where('event', AuditEvent::ImpersonationStarted)->sole();
        $this->assertSame($this->admin->id, $started->user_id);
        $this->assertFalse(AuditLog::where('event', AuditEvent::Login)->exists(), 'Not logged as the user signing in.');

        // Actions meanwhile carry the admin.
        $this->get(route('exports.download', 'sirketler'))->assertOk();
        $this->assertSame($this->admin->id, AuditLog::where('event', AuditEvent::DataExported)->sole()->properties['impersonated_by'] ?? null);

        $this->post(route('impersonation.stop'))->assertRedirect(Portal::Admin->url("kullanicilar/{$this->client->id}"));
        $this->assertGuest();
        $this->assertTrue(AuditLog::where('event', AuditEvent::ImpersonationEnded)->exists());
        $this->assertFalse(AuditLog::where('event', AuditEvent::Logout)->exists());
    }

    public function test_account_settings_and_kvkk_decisions_are_off_limits(): void
    {
        $this->seed(KvkkSeeder::class); // the client has not decided yet

        $this->get(route('impersonation.start', Impersonation::issue($this->admin, $this->client)));

        // Not sent to the consent screen, and cannot decide on the user's behalf.
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('kvkk.consent'))->assertRedirect(route('dashboard'));
        $this->get(route('profile.edit'))->assertRedirect(route('dashboard'));
        $this->get(route('security.edit'))->assertRedirect(route('dashboard'));
        $this->get(route('kvkk.edit'))->assertRedirect(route('dashboard'));
    }

    public function test_session_expires_after_the_limit(): void
    {
        $this->get(route('impersonation.start', Impersonation::issue($this->admin, $this->client)));

        $this->travel(Impersonation::MAX_MINUTES + 1)->minutes();

        $this->get(route('dashboard'))->assertRedirect(Portal::Admin->url("kullanicilar/{$this->client->id}"));
        $this->assertGuest();
        $this->assertStringContainsString('süre doldu', AuditLog::where('event', AuditEvent::ImpersonationEnded)->sole()->description);
    }

    public function test_tokens_expire_and_targets_are_restricted(): void
    {
        $token = Impersonation::issue($this->admin, $this->client);
        $this->travel(Impersonation::TOKEN_SECONDS + 1)->seconds();
        $this->get(route('impersonation.start', $token))->assertForbidden();

        foreach ([
            [$this->admin, User::factory()->superAdmin()->create()],
            [$this->admin, $this->admin],
            [$this->admin, User::factory()->inactive()->create()],
            [User::factory()->payrollSpecialist()->create(), $this->client],
        ] as [$by, $target]) {
            try {
                Impersonation::issue($by, $target);
                $this->fail('Expected refusal.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_admin_user_page_offers_the_button(): void
    {
        $this->onPortal(Portal::Admin);
        $this->actingAs($this->admin);

        $this->get(route('admin.users.show', $this->client))->assertOk()->assertSee('Kullanıcı olarak görüntüle');
        $this->get(route('admin.users.show', User::factory()->superAdmin()->create()))->assertDontSee('Kullanıcı olarak görüntüle');

        Livewire::test('pages::admin.users.show', ['user' => $this->client])
            ->call('impersonate')
            ->assertHasNoErrors();
    }
}
