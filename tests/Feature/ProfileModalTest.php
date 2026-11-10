<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ProfileModalTest extends TestCase {
 use RefreshDatabase;
 public function test_profile_modal_contains_real_user_details_and_readonly_email(): void {
  $user=User::factory()->create(['name'=>'Profile Test','email'=>'profile@example.com']);
  $user->setAttribute('avatar_url','https://example.com/google-picture.jpg');
  $this->actingAs($user)->get('/')->assertOk()->assertSee('Profile Test')->assertSee('profile@example.com')->assertSee('google-picture.jpg')->assertSee('id="profile-email" name="email" type="email" readonly',false)->assertSee('id="profile-phone"',false)->assertSee('data-profile-edit',false)->assertSee('data-profile-save',false)->assertSee('data-profile-cancel',false);
 }
 public function test_profile_modal_uses_native_locale_and_has_no_submission_endpoint(): void {
  $user=User::factory()->create();
  foreach(['en','th','ko'] as $locale){$user->preferred_language=$locale;$user->save();$response=$this->actingAs($user)->get('/');$response->assertOk()->assertSee(__('Phone Number',[],$locale))->assertSee(__('Edit Profile',[],$locale))->assertSee('<form class="profile-form" novalidate>',false);}
 }
}
