<?php

namespace Tests\Feature\Admin;

use App\Actions\Firms\ReviewFirm;
use App\Actions\Workplaces\RevealWorkplaceCredential;
use App\Actions\Workplaces\SaveWorkplace;
use App\Enums\AuditEvent;
use App\Enums\Portal;
use App\Http\Middleware\SecureAdminPortal;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workplace;
use App\Support\SecuritySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function adminLogin(string $email, string $password = 'password'): TestResponse
    {
        return $this->post('https://admin.oigopayroll.test/login', ['email' => $email, 'password' => $password]);
    }

    public function test_failed_logins_are_logged_with_reason_but_the_user_sees_one_generic_message(): void
    {
        $admin = User::factory()->superAdmin()->create(['email' => 'admin@hrd.com']);
        $client = User::factory()->create(['email' => 'client@firma.com']);
        $inactive = User::factory()->superAdmin()->inactive()->create(['email' => 'eski@hrd.com']);

        foreach ([
            ['kimse@yok.com', 'password', 'unknown_user'],
            ['admin@hrd.com', 'yanlis', 'wrong_password'],
            ['client@firma.com', 'password', 'wrong_portal'],
            ['eski@hrd.com', 'password', 'inactive'],
        ] as [$email, $password, $reason]) {
            $this->adminLogin($email, $password)->assertSessionHasErrors(['email' => __('auth.failed')]);

            $log = AuditLog::where('event', AuditEvent::LoginFailed)->latest('id')->firstOrFail();
            $this->assertSame($reason, $log->properties['reason']);
            $this->assertSame($email, $log->properties['email']);
            $this->assertSame('admin', $log->portal);
            $this->assertSame('127.0.0.1', $log->ip_address);
        }

        $this->assertGuest();
        unset($admin, $client, $inactive);
    }

    public function test_successful_login_and_logout_are_logged(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->adminLogin($admin->email)->assertSessionHasNoErrors();
        $this->post('https://admin.oigopayroll.test/logout');

        $this->assertSame(1, AuditLog::where('event', AuditEvent::Login)->where('user_id', $admin->id)->count());
        $this->assertSame(1, AuditLog::where('event', AuditEvent::Logout)->where('user_id', $admin->id)->count());
    }

    public function test_too_many_attempts_lock_out_with_a_message_and_are_logged(): void
    {
        Setting::putMany([SecuritySettings::ATTEMPTS_PER_MINUTE => 3]);
        User::factory()->superAdmin()->create(['email' => 'admin@hrd.com']);

        foreach (range(1, 3) as $attempt) {
            $this->adminLogin('admin@hrd.com', 'yanlis');
        }

        $this->adminLogin('admin@hrd.com', 'password')
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('event', AuditEvent::Lockout)->count());
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('https://panel.oigopayroll.test/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Strict-Transport-Security');
    }

    public function test_ip_allowlist_blocks_admin_but_not_panel(): void
    {
        Setting::putMany([SecuritySettings::IP_ALLOWLIST => "10.0.0.0/8\n85.105.12.34"]);

        $this->get('https://admin.oigopayroll.test/login')->assertForbidden();
        $this->get('https://panel.oigopayroll.test/login')->assertOk();
        $this->assertSame(1, AuditLog::where('event', AuditEvent::IpBlocked)->count());

        Setting::putMany([SecuritySettings::IP_ALLOWLIST => "127.0.0.1\n10.0.0.0/8"]);
        $this->get('https://admin.oigopayroll.test/login')->assertOk();
    }

    public function test_admin_cannot_save_an_allowlist_that_locks_them_out(): void
    {
        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test('pages::admin.security.index')
            ->set('tab', 'ayarlar')
            ->set('ipAllowlist', "85.105.12.34\nbozuk-ip")
            ->call('saveSettings')
            ->assertHasErrors('ipAllowlist')
            ->set('ipAllowlist', '85.105.12.34')
            ->call('saveSettings')
            ->assertHasErrors('ipAllowlist')
            ->set('ipAllowlist', "85.105.12.34\n127.0.0.1")
            ->set('idleMinutes', '15')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertSame(['85.105.12.34', '127.0.0.1'], SecuritySettings::adminIpAllowlist());
        $this->assertSame(15, SecuritySettings::adminIdleMinutes());
        $this->assertSame(1, AuditLog::where('event', AuditEvent::SettingsChanged)->count());
    }

    public function test_idle_admin_session_is_closed(): void
    {
        Setting::putMany([SecuritySettings::IDLE_MINUTES => 10]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->withSession([SecureAdminPortal::LAST_ACTIVITY => now()->subMinutes(11)->getTimestamp()])
            ->get('https://admin.oigopayroll.test/')
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('event', AuditEvent::IdleLogout)->count());
    }

    public function test_two_factor_can_be_required_for_admins(): void
    {
        $this->onPortal(Portal::Admin);
        Setting::putMany([SecuritySettings::TWO_FACTOR_REQUIRED => true]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('https://admin.oigopayroll.test/firmalar')->assertRedirect(route('security.edit'));

        // The 2FA setup path stays reachable (security settings ask for password confirmation first).
        $this->actingAs($admin)->get('https://admin.oigopayroll.test/settings/security')->assertRedirect('https://admin.oigopayroll.test/user/confirm-password');
        $this->actingAs($admin)->get('https://admin.oigopayroll.test/user/confirm-password')->assertOk();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->get('https://admin.oigopayroll.test/settings/security')->assertOk();

        // The panel is not affected; admins with 2FA pass.
        $this->actingAs($admin)->get('https://panel.oigopayroll.test/')->assertOk();
        $this->actingAs(User::factory()->superAdmin()->withTwoFactor()->create())
            ->get('https://admin.oigopayroll.test/firmalar')->assertOk();
    }

    public function test_sessions_can_be_terminated(): void
    {
        $this->onPortal(Portal::Admin);
        $admin = User::factory()->superAdmin()->create();
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'other-1', 'user_id' => $other->id, 'ip_address' => '1.2.3.4', 'user_agent' => 'Mozilla/5.0 (Windows) Chrome/140', 'payload' => '', 'last_activity' => now()->getTimestamp()],
            ['id' => 'other-2', 'user_id' => $other->id, 'ip_address' => '1.2.3.5', 'user_agent' => 'Mozilla/5.0 (iPhone) Safari/1', 'payload' => '', 'last_activity' => now()->getTimestamp()],
        ]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.security.index')
            ->set('tab', 'oturumlar')
            ->assertSee('Chrome · Windows')
            ->call('terminateSession', 'other-1')
            ->call('terminateUserSessions', $other->id);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->assertSame(2, AuditLog::where('event', AuditEvent::SessionTerminated)->count());
    }

    public function test_actions_are_audited_without_leaking_credentials(): void
    {
        $workplace = Workplace::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        app(RevealWorkplaceCredential::class)->handle($workplace, 'sgk_system_password', $admin);

        $input = collect($workplace->getAttributes())->except(['id', 'company_id', 'created_by', 'created_at', 'updated_at', 'deleted_at'])->all();
        $input['opening_date'] = $workplace->opening_date->format('Y-m-d');
        $input['sgk_system_password'] = 'YENI-GIZLI-SIFRE';
        foreach (Workplace::SECRET_FIELDS as $field) {
            if ($field !== 'sgk_system_password') {
                unset($input[$field]);
            }
        }
        app(SaveWorkplace::class)->update($workplace, $input);

        $this->assertSame(1, AuditLog::where('event', AuditEvent::CredentialRevealed)->count());
        $update = AuditLog::where('event', AuditEvent::WorkplaceUpdated)->sole();
        $this->assertContains('sgk_system_password', $update->properties['fields']);
        $this->assertStringNotContainsString('YENI-GIZLI-SIFRE', (string) DB::table('audit_logs')->pluck('properties')->implode(' '));
        $this->assertStringNotContainsString('YENI-GIZLI-SIFRE', (string) DB::table('audit_logs')->pluck('description')->implode(' '));
    }

    public function test_security_page_lists_events_failures_and_credential_reveals(): void
    {
        $this->onPortal(Portal::Admin);
        $admin = User::factory()->superAdmin()->create(['name' => 'Bakan Admin']);
        $firm = Firm::factory()->pending()->create(['name' => 'İzlenen Firma']);
        $workplace = Workplace::factory()->create(['branch_name' => 'Kadıköy Şube']);
        $this->actingAs($admin);

        app(ReviewFirm::class)->approve($firm, $admin);
        app(RevealWorkplaceCredential::class)->handle($workplace, 'sgk_system_password', $admin);
        $this->post('https://admin.oigopayroll.test/logout');
        $this->adminLogin('saldirgan@example.com', 'x');
        $this->actingAs($admin);

        $this->get(route('admin.security.index'))->assertOk()->assertSee('role="tablist"', false)
            ->assertSee('Firma onaylandı: İzlenen Firma');
        $this->get(route('admin.security.index', ['sekme' => 'basarisiz']))->assertOk()
            ->assertSee('saldirgan@example.com')->assertSee('Kayıtlı olmayan e-posta');
        $this->get(route('admin.security.index', ['sekme' => 'sifreler']))->assertOk()
            ->assertSee('Kadıköy Şube')->assertSee('SGK Sistem Şifresi');

        Livewire::test('pages::admin.security.index')
            ->set('group', 'firm')
            ->assertSee('İzlenen Firma')
            ->assertDontSee('Çıkış yapıldı');

        $this->get(route('admin.dashboard'))->assertSee('Başarısız giriş (24 saat)');
    }

    public function test_non_admins_cannot_open_the_security_page(): void
    {
        $this->actingAs(User::factory()->payrollSpecialist()->create());

        Livewire::test('pages::admin.security.index')->assertForbidden();
    }
}
