<?php

namespace Tests\Feature;

use App\Enums\Portal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_is_resolved_from_host(): void
    {
        $this->assertSame(Portal::Admin, Portal::fromHost('admin.oigopayroll.test'));
        $this->assertSame(Portal::Panel, Portal::fromHost('PANEL.oigopayroll.test'));
        $this->assertSame(Portal::Landing, Portal::fromHost('oigopayroll.test'));
        $this->assertSame(Portal::Landing, Portal::fromHost('unknown.example.com'));
    }

    public function test_plain_http_is_redirected_to_https(): void
    {
        $this->get('http://admin.oigopayroll.test/login?x=1')
            ->assertStatus(301)
            ->assertRedirect('https://admin.oigopayroll.test/login?x=1')
            ->assertCookieMissing(config('session.cookie'));
    }

    public function test_landing_page_is_served_and_auth_pages_move_to_the_panel(): void
    {
        $this->get('https://oigopayroll.test/')->assertOk();

        $this->get('https://oigopayroll.test/login')->assertRedirect('https://panel.oigopayroll.test/login');
        $this->get('https://oigopayroll.test/register')->assertRedirect('https://panel.oigopayroll.test/register');
    }

    public function test_each_portal_shows_its_own_login_page(): void
    {
        $this->get('https://admin.oigopayroll.test/login')->assertOk()->assertSee('HRD Yönetim Paneli');
        $this->get('https://panel.oigopayroll.test/login')->assertOk()->assertSee('Müşteri Paneli');
    }

    public function test_registration_is_only_available_on_the_panel(): void
    {
        $this->get('https://panel.oigopayroll.test/register')->assertOk();
        $this->get('https://admin.oigopayroll.test/register')->assertNotFound();
        $this->post('https://admin.oigopayroll.test/register', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_super_admin_can_sign_in_on_admin_and_panel(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->post('https://admin.oigopayroll.test/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($admin);

        $this->post('https://admin.oigopayroll.test/logout');
        $this->assertGuest();

        $this->post('https://panel.oigopayroll.test/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($admin);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function panelOnlyUsers(): array
    {
        return [
            'client user' => ['client_user'],
            'payroll specialist' => ['payroll_specialist'],
        ];
    }

    #[DataProvider('panelOnlyUsers')]
    public function test_other_users_can_only_sign_in_on_panel(string $type): void
    {
        $user = User::factory()->create(['type' => $type]);

        $this->post('https://admin.oigopayroll.test/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('https://panel.oigopayroll.test/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_users_cannot_sign_in(): void
    {
        $client = User::factory()->inactive()->create();

        $this->post('https://panel.oigopayroll.test/login', ['email' => $client->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_signed_in_user_of_the_wrong_type_is_logged_out(): void
    {
        $this->actingAs(User::factory()->payrollSpecialist()->create())
            ->get('https://admin.oigopayroll.test/')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_self_registered_users_are_client_users(): void
    {
        $this->post('https://panel.oigopayroll.test/register', [
            'name' => 'Yeni Müşteri',
            'email' => 'musteri@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertSame('client_user', User::where('email', 'musteri@example.com')->value('type')?->value);
    }
}
