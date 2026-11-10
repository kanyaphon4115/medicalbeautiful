<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_read_and_change_accounts(): void
    {
        $target = User::factory()->create();
        foreach (['user', 'hospital'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->getJson('/admin/accounts')->assertForbidden();
            $this->postJson('/admin/accounts', [])->assertForbidden();
            $this->patchJson('/admin/accounts/'.$target->id, [])->assertForbidden();
        }
    }

    public function test_accounts_are_loaded_created_and_updated_in_database(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/admin/accounts')->assertOk()
            ->assertJsonFragment(['id' => $admin->id, 'email' => $admin->email])
            ->assertJsonMissingPath('accounts.0.password');
        $data = ['name' => 'Hospital', 'email' => 'hospital@example.test', 'role' => 'hospital', 'password' => 'password123'];
        $id = $this->postJson('/admin/accounts', $data)->assertOk()->json('account.id');
        $user = User::findOrFail($id);
        $this->assertTrue(Hash::check('password123', $user->password));
        unset($data['password']);
        $data['role'] = 'user';
        $data['name'] = 'Updated';
        $this->patchJson('/admin/accounts/'.$id, $data)->assertOk();
        $this->assertDatabaseHas('users', ['id' => $id, 'name' => 'Updated', 'role' => 'user']);
        $this->assertSame($user->password, $user->fresh()->password);
        $this->postJson('/admin/accounts', $data)->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $data['role'] = 'finance';
        $this->patchJson('/admin/accounts/'.$id, $data)->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->patchJson('/admin/accounts/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'user'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
    }
}
