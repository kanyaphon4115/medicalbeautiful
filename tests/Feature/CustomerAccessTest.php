<?php
namespace Tests\Feature;
use Tests\TestCase;
class CustomerAccessTest extends TestCase
{
    public function test_public_home_keeps_browsing_request_entry_and_how_it_works_available(): void
    {
        $this->get('/')->assertOk()->assertSee('data-how-it-works',false)->assertSee('data-customer-feature="My Requests"',false)
            ->assertSee('href="'.route('login').'"',false)->assertDontSee('href="'.url('/').'#journey/sign-in"',false)->assertSee('data-partner-directory',false)->assertSee('data-quote',false)->assertDontSee('href="'.route('register').'"',false);
    }
    public function test_real_private_endpoints_still_enforce_authentication(): void
    {
        foreach(['/my-requests','account/settings','chat/messages'] as $url) $this->getJson($url)->assertUnauthorized();
        $this->patchJson('/account/profile',['name'=>'Guest'])->assertUnauthorized();
        $this->get('/my-requests')->assertRedirect(route('login'));
    }
    public function test_public_registration_url_opens_guide_instead_of_a_signup_form(): void
    {
        $this->get('/register')->assertRedirect(route('home').'#how-it-works');
        $this->postJson('/register',[])->assertForbidden();
    }
}
