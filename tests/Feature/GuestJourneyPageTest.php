<?php
namespace Tests\Feature;
use Tests\TestCase;
class GuestJourneyPageTest extends TestCase
{
    public function test_guest_journey_is_mounted_inside_the_existing_homepage(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('data-guest-journey', false)
            ->assertSee('data-partner-directory', false)
            ->assertDontSee('data-notification-demo=', false)
            ->assertSee('data-real-request-store=', false)
            ->assertDontSee('data-mail-store=', false)
            ->assertSee('#journey/new?procedure=Eye%20Surgery', false)
            ->assertSee('#journey/new?procedure=Rhinoplasty', false);
    }
}
