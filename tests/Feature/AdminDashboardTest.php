<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menu_and_dashboard_require_admin_role(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->get('/')->assertDontSee('data-admin-nav', false);

        foreach ([User::ROLE_USER, User::ROLE_HOSPITAL] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/')->assertOk()->assertDontSee('data-admin-nav', false);
            $this->get('/admin/dashboard')->assertForbidden();
        }

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $response = $this->actingAs($admin)->get('/')->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'data-admin-nav'));
        $response->assertSee(route('admin.dashboard'));
        $this->get('/admin/dashboard')->assertOk()->assertSee('Here is the platform overview.')
            ->assertSee('Frontend demo')->assertSee('ap-data');
    }

    public function test_all_portal_pages_render_in_three_languages_without_loading_real_patient_records(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        User::factory()->create(['email' => 'hidden-patient@example.test']);
        foreach (['en', 'th', 'ko'] as $locale) {
            foreach (\App\Http\Controllers\AdminPortalController::PAGES as $page => $label) {
                $this->get('/admin/'.$page.'?lang='.$locale)->assertOk()
                    ->assertSee('<html lang="'.$locale.'">', false)
                    ->assertSee('data-page="'.$page.'"', false)
                    ->assertDontSee('hidden-patient@example.test');
            }
        }
    }

    public function test_each_admin_page_denies_other_roles(): void
    {
        foreach ([User::ROLE_USER, User::ROLE_HOSPITAL] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (array_keys(\App\Http\Controllers\AdminPortalController::PAGES) as $page) {
                $this->get('/admin/'.$page)->assertForbidden();
            }
        }
    }
}
