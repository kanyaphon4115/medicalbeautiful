<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLanguageSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_shows_all_native_language_names_and_defaults_to_english(): void
    {
        $this->get('/')->assertOk()->assertSee('data-language-switcher', false)
            ->assertSee('English')->assertSee('ไทย')->assertSee('한국어')
            ->assertSee('<html lang="en">', false);
    }

    public function test_guest_selection_persists_across_refresh_and_customer_pages(): void
    {
        foreach (['th', 'ko', 'en'] as $locale) {
            $this->postJson(route('website.language'), ['language' => $locale])
                ->assertOk()->assertJsonPath('language', $locale)
                ->assertSessionHas('website_locale', $locale)
                ->assertCookie('website_language', $locale);
            foreach (['/', '/patient-reviews', '/login', '/forgot-password'] as $path) {
                $this->get($path)->assertOk()->assertSee('<html lang="'.$locale.'">', false);
            }
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_guest_cookie_restores_language_in_a_new_session(): void
    {
        $this->withCookie('website_language', 'ko')->get('/')->assertOk()
            ->assertSee('<html lang="ko">', false);
    }

    public function test_signed_in_selection_changes_only_the_language_preference(): void
    {
        $user = User::factory()->create(['name' => 'My Requests', 'notification_preferences' => ['requests'=>false,'quotations'=>true,'messages'=>false]]);
        $before = $user->fresh()->getAttributes();
        $this->actingAs($user);
        foreach (['th', 'ko', 'en'] as $locale) {
            $this->postJson(route('website.language'), ['language' => $locale, 'name' => 'Changed'])
                ->assertOk()->assertCookie('website_language', $locale);
            $this->assertSame($locale, $user->fresh()->preferred_language);
            $this->get('/my-requests')->assertOk()->assertSee('<html lang="'.$locale.'">', false);
        }
        $after = $user->fresh()->getAttributes();
        unset($before['preferred_language'], $before['updated_at'], $after['preferred_language'], $after['updated_at']);
        $this->assertSame($before, $after);
    }

    public function test_unsupported_language_cannot_change_the_saved_preference(): void
    {
        $user = User::factory()->create(['preferred_language' => 'th']);
        $this->actingAs($user)->postJson(route('website.language'), ['language' => 'kr'])
            ->assertUnprocessable()->assertJsonValidationErrors('language');
        $this->assertSame('th', $user->fresh()->preferred_language);
    }

    public function test_no_javascript_submission_returns_to_the_same_page_in_the_selected_language(): void
    {
        $this->from('/patient-reviews?lang=en&rating=5')->post(route('website.language'), ['language' => 'th'])
            ->assertRedirect('/patient-reviews?lang=th&rating=5')->assertCookie('website_language', 'th');
    }

    public function test_customer_notifications_translate_the_interface_but_keep_codes_and_private_notes(): void
    {
        $user = User::factory()->create(['preferred_language' => 'ko']);
        $this->assertSame('ko', $user->preferredLocale());
        foreach (['en', 'th', 'ko'] as $locale) {
            app()->setLocale($locale);
            $booking = (new \App\Notifications\BookingDepositApproved('My Requests', 'Pending', 300000, 'http://localhost/bookings', 1000000, 200000, 300000, 500000))->toMail($user);
            $this->assertSame(__('Booking Confirmed | AESTHETIC'), $booking->subject);
            $this->assertStringContainsString('My Requests', implode(' ', $booking->introLines));
            $code = (new \App\Notifications\GuestJourneyCode('123456', true))->toMail($user);
            $this->assertStringContainsString('123456', implode(' ', $code->introLines));
            $evidence = (new \App\Notifications\PaymentEvidenceRequested('http://localhost/payment', 'My Requests'))->toMail($user);
            $this->assertContains('My Requests', $evidence->introLines);
            if ($locale !== 'en') {
                $this->assertNotSame('Booking Confirmed | AESTHETIC', $booking->subject);
                $this->assertNotSame('Dear Customer,', $booking->greeting);
                $this->assertNotSame('Submit Requested Payment Evidence', $evidence->actionText);
            }
        }
    }
}
