<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_email_token_can_change_password_only_once(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email'=>$user->email])->assertRedirect();
        $token = null;
        Notification::assertSentTo($user, PasswordResetCode::class, function ($notification) use (&$token) {
            $token = $notification->code;
            return true;
        });
        $this->get('/reset-password')->assertRedirect(route('password.request'));
        $this->get('/verify-reset-code?email='.urlencode($user->email))->assertOk();
        $data = ['code'=>$token, 'email'=>$user->email, 'password'=>'new-password123', 'password_confirmation'=>'new-password123'];
        $this->post('/verify-reset-code', array_replace($data, ['code'=>'9999999']))->assertSessionHasErrors('code');
        $this->post('/verify-reset-code', $data)->assertRedirect(route('password.reset'));
        $this->get('/reset-password')->assertOk()->assertDontSee('name="code"', false);
        $this->post('/reset-password', $data)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->post('/verify-reset-code', $data)->assertSessionHasErrors('code');
    }

    public function test_unknown_email_has_same_public_response(): void
    {
        Notification::fake();
        $this->post('/forgot-password', ['email'=>'unknown@example.test'])->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_code_expires_and_wrong_attempts_are_limited(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email'=>$user->email]);
        $code = Notification::sent($user, PasswordResetCode::class)->first()->code;
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        $data = ['email'=>$user->email, 'code'=>$code, 'password'=>'new-password123', 'password_confirmation'=>'new-password123'];
        $this->travel(6)->minutes();
        $this->post('/verify-reset-code', $data)->assertSessionHasErrors('code');
        $this->travelBack();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/verify-reset-code', array_replace($data, ['code'=>$code==='000000'?'111111':'000000']))->assertSessionHasErrors('code');
        }
        $this->post('/verify-reset-code', $data)->assertSessionHasErrors('code');
        $this->assertFalse(Hash::check($data['password'], $user->fresh()->password));
    }
}
