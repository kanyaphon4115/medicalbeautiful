<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret', 'services.google.redirect' => 'http://localhost/auth/google/callback']);
    }

    private function googleUser(bool $verified = true): GoogleUser
    {
        return (new GoogleUser())->setRaw(['email_verified' => $verified])
            ->map(['id' => 'google-test-123', 'name' => 'Google Member', 'email' => 'member@gmail.com']);
    }

    private function mockGoogle(GoogleUser $user): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }

    public function test_existing_google_hospital_account_lands_on_dashboard(): void
    {
        $hospital = User::factory()->create([
            'email' => 'member@gmail.com', 'google_id' => 'google-test-123',
            'role' => User::ROLE_HOSPITAL,
        ]);
        $this->mockGoogle($this->googleUser());
        $this->get('/auth/google/callback')->assertRedirect(route('hospital.dashboard'));
        $this->assertAuthenticatedAs($hospital);
        $this->assertSame(User::ROLE_HOSPITAL, $hospital->fresh()->role);
    }

    public function test_missing_configuration_displays_a_useful_error(): void
    {
        config(['services.google.client_id' => null]);
        $this->get('/auth/google')->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_redirect_includes_state_and_only_identity_scopes(): void
    {
        $response = $this->get('/auth/google');
        $response->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertSame('accounts.google.com', parse_url($url, PHP_URL_HOST));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertNotEmpty($query['state']);
        $this->assertSame(session('state'), $query['state']);
        $this->assertSame('select_account', $query['prompt']);
        $this->assertStringNotContainsString('drive', $query['scope']);
    }

    public function test_callback_without_valid_state_cannot_sign_in(): void
    {
        $this->get('/auth/google/callback?code=invalid&state=invalid')
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_verified_google_identity_creates_and_reuses_an_account(): void
    {
        $this->mockGoogle($this->googleUser());
        $this->get('/auth/google/callback')->assertRedirect(route('home'));
        $user = User::where('google_id', 'google-test-123')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseCount('users', 1);
        Auth::logout();
        $this->mockGoogle($this->googleUser());
        $this->get('/auth/google/callback')->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_cannot_silently_link_an_existing_email_account(): void
    {
        $existing = User::factory()->create(['email' => 'member@gmail.com']);
        $this->mockGoogle($this->googleUser());
        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertNull($existing->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_unverified_email_and_cancelled_consent_are_rejected(): void
    {
        $this->mockGoogle($this->googleUser(false));
        $this->get('/auth/google/callback')->assertSessionHasErrors('google');
        $this->get('/auth/google/callback?error=access_denied')->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
