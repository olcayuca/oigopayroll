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

    public function test_super_admin_can_visit_the_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_payroll_specialists_work_in_the_panel_not_the_admin(): void
    {
        $specialist = User::factory()->payrollSpecialist()->create();

        $this->actingAs($specialist)->get(route('dashboard'))->assertOk();
        $this->actingAs($specialist)->get(route('admin.dashboard'))->assertRedirect('/login');
    }
}
