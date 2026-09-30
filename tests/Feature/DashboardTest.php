<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page_of_the_same_portal(): void
    {
        $this->get(route('dashboard'))->assertRedirect('https://panel.oigopayroll.test/login');
        $this->get(route('admin.dashboard'))->assertRedirect('https://admin.oigopayroll.test/login');
    }

    public function test_client_users_can_visit_the_panel_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_hrd_staff_can_visit_the_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->payrollSpecialist()->create())
            ->get(route('admin.dashboard'))
            ->assertOk();
    }
}
