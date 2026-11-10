<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    public function test_existing_sign_in_keeps_google_and_shows_guide_instead_of_public_signup(): void
    {
        foreach ([['/login', 'Sign In', '/login', 'Welcome Back']] as [$path, $title, $otherPath, $heading]) {
            $response = $this->get($path);

            $response->assertOk()
                ->assertSee($title)
                ->assertSee($heading)
                ->assertSee('Continue with Google')
                ->assertSee('data-how-it-works', false)
                ->assertDontSee('href="'.route('register').'"', false)
                ->assertSee('Verified Hospitals')
                ->assertSee('Expert Consultation')
                ->assertSee('Secure &amp; Safe', false)
                ->assertSee('data-auth-form', false)
                ->assertSee('type="email"', false)
                ->assertSee('type="password"', false)
                ->assertDontSee('Continue with Apple')
                ->assertDontSee('Continue with Facebook');

            $this->assertSame(1, substr_count($response->getContent(), 'data-google-auth'));
        }
    }
}
