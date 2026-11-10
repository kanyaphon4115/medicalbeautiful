<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;
    public function test_new_users_default_to_normal_role_and_cannot_mass_assign_admin(): void
    {
        $user=User::create(['name'=>'admin','email'=>'normal@example.test','password'=>'SafePassword123','role'=>'admin']);
        $user->refresh();
        $this->assertSame('user',$user->role);
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isChatSupport());
    }
    public function test_registration_cannot_request_admin_role(): void
    {
        $this->post('/register',['name'=>'Normal','email'=>'register@example.test','password'=>'SafePassword123',
            'password_confirmation'=>'SafePassword123','terms'=>'1','role'=>'admin'])->assertForbidden();
        $this->assertDatabaseMissing('users',['email'=>'register@example.test']);
    }
    public function test_only_explicit_admin_role_grants_support_access(): void
    {
        $user=User::factory()->create();
        foreach(['user','hospital','ADMIN','staff','unknown'] as $role) {
            $user->forceFill(['role'=>$role])->save();
            $this->assertFalse($user->isChatSupport());
        }
        $user->forceFill(['role'=>'admin'])->save();
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isChatSupport());
    }

    public function test_hospital_role_is_persisted_without_admin_or_support_access(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => User::ROLE_HOSPITAL])->save();
        $user = $user->fresh();

        $this->assertSame('hospital', $user->role);
        $this->assertTrue($user->isHospital());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isChatSupport());
        $this->assertSame('Hospital', $user->roleLabel());
        $this->actingAs($user)->get('/')->assertOk()->assertSee('(Hospital)');
    }

    public function test_registration_and_profile_cannot_grant_hospital_role(): void
    {
        $this->post('/register', [
            'name' => 'Customer', 'email' => 'customer@example.test',
            'password' => 'SafePassword123', 'password_confirmation' => 'SafePassword123',
            'terms' => '1', 'role' => User::ROLE_HOSPITAL,
        ])->assertForbidden();
        $this->assertDatabaseMissing('users',['email'=>'customer@example.test']);
        $user = User::factory()->create(['email'=>'customer@example.test']);
        $this->assertSame(User::ROLE_USER, $user->fresh()->role);

        $this->actingAs($user)->patchJson('/account/profile', [
            'name' => 'Customer', 'phone' => '', 'country' => '',
            'language' => 'en', 'role' => User::ROLE_HOSPITAL,
        ])->assertOk();
        $this->assertFalse($user->fresh()->isHospital());
    }
}
