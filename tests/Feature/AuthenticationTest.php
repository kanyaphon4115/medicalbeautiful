<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_blocked_without_creating_an_account(): void
    {
        $this->postJson('/register', ['name'=>'Test Member','email'=>'member@example.com','password'=>'StrongPassword123','password_confirmation'=>'StrongPassword123','terms'=>'1'])->assertForbidden();
        $this->assertGuest();
        $this->assertDatabaseCount('users',0);
        $this->get('/register')->assertRedirect(route('home').'#how-it-works');
    }

    public function test_invalid_registration_cannot_bypass_the_customer_journey(): void
    {
        User::factory()->create(['email'=>'member@example.com']);
        $this->post('/register',['email'=>'member@example.com','role'=>'admin'])->assertForbidden();
        $this->assertGuest();
        $this->assertDatabaseCount('users',1);
    }

    public function test_a_member_can_sign_in_and_sign_out(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com', 'password' => Hash::make('correct-password')]);
        $this->post('/login', ['email' => 'MEMBER@EXAMPLE.COM', 'password' => 'correct-password'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
        $this->get('/')->assertDontSee('class="account-name"', false);
    }

    public function test_wrong_password_is_rejected_and_repeated_attempts_are_limited(): void
    {
        User::factory()->create(['email' => 'member@example.com']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', ['email' => 'member@example.com', 'password' => 'incorrect-password'])
                ->assertRedirect('/login')->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'member@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Too many sign-in attempts.', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_oversized_registration_payload_does_not_create_an_account(): void
    {
        $password = str_repeat('a', 73);
        $this->post('/register', ['name' => 'Test', 'email' => 'member@example.com', 'password' => $password, 'password_confirmation' => $password, 'terms' => '1'])
            ->assertForbidden();
        $this->assertDatabaseCount('users', 0);
    }
}
