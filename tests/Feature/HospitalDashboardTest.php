<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospital_navigation_is_visible_only_to_hospital_accounts(): void
    {
        $this->get('/')->assertOk()->assertDontSee('data-hospital-nav', false);
        foreach ([User::ROLE_USER, User::ROLE_ADMIN, User::ROLE_HOSPITAL] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get('/')->assertOk();
            if ($role === User::ROLE_HOSPITAL) {
                $this->assertSame(2, substr_count($response->getContent(), 'data-hospital-nav'));
                $response->assertSee(route('hospital.dashboard'));
            } else {
                $response->assertDontSee('data-hospital-nav', false);
            }
        }
    }

    public function test_hospital_password_login_opens_dashboard(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_HOSPITAL, 'password' => 'SafePassword123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'SafePassword123'])
            ->assertRedirect(route('hospital.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_signed_in_hospital_visiting_login_is_sent_to_dashboard(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_HOSPITAL]);
        $this->actingAs($user)->get('/login')->assertRedirect(route('hospital.dashboard'));
        $this->get('/register')->assertRedirect(route('hospital.dashboard'));
    }





    public function test_dashboard_requires_sign_in(): void
    {
        $this->get('/hospital/dashboard')->assertRedirect('/login');
    }

    public function test_only_hospital_accounts_can_open_dashboard(): void
    {
        foreach ([User::ROLE_USER, User::ROLE_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/hospital/dashboard')->assertForbidden();
        }
    }

    public function test_hospital_dashboard_renders_empty_real_data_without_exposing_unrelated_customers(): void
    {
        $hospital = User::factory()->create(['name' => 'Hospital Partner', 'role' => User::ROLE_HOSPITAL]);
        User::factory()->create(['name' => 'Private Customer', 'email' => 'private@example.test']);

        $this->actingAs($hospital)->get('/hospital/dashboard')->assertOk()
            ->assertSee('Hospital Partner')->assertSee('Recent Patient Requests')
            ->assertSee('No patient requests available.')->assertDontSee('Demo preview')->assertDontSee('Private Customer')
            ->assertDontSee('private@example.test');
    }
}
