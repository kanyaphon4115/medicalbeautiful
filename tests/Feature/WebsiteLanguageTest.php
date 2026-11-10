<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class WebsiteLanguageTest extends TestCase {
 use RefreshDatabase;
 public function test_default_website_language_is_english(): void {$this->get('/')->assertOk()->assertSee('<html lang="en">',false);}
 public function test_saved_language_is_used_for_pages_and_can_be_changed_back(): void {
  $user=User::factory()->create();$user->preferred_language='th';$user->save();
  $this->actingAs($user)->get('/')->assertOk()->assertSee('<html lang="th">',false);
  $this->get('/my-requests')->assertOk()->assertSee('<html lang="th">',false);
  $this->patchJson('/account/settings',['notifications'=>['requests'=>true,'quotations'=>true,'messages'=>true],'language'=>'en'])->assertOk()->assertSessionHas('website_locale','en')->assertCookie('website_language','en');
  $this->get('/')->assertOk()->assertSee('<html lang="en">',false);
 }
 public function test_korean_can_be_saved_and_used_on_website_pages(): void {
  $user=User::factory()->create();
  $this->actingAs($user)->patchJson('/account/settings',['notifications'=>['requests'=>true,'quotations'=>true,'messages'=>true],'language'=>'ko'])->assertOk()->assertSessionHas('website_locale','ko')->assertCookie('website_language','ko');
  $this->assertSame('ko',$user->fresh()->preferred_language);
  $this->get('/')->assertOk()->assertSee('<html lang="ko">',false);
  $this->get('/my-requests')->assertOk()->assertSee('<html lang="ko">',false);
 }
 public function test_blade_renders_native_translations_without_translating_submission_codes(): void {
  $user=User::factory()->create();
  foreach(['th','ko'] as $locale) {
   $user->preferred_language=$locale;$user->save();
   $this->actingAs($user)->get('/')->assertOk()->assertSee(__('My Requests',[],$locale))->assertSee(__('Your Beauty,',[],$locale))->assertSee('data-procedure="Eye Surgery"',false);
   $this->get('/my-requests')->assertOk()->assertSee(__('Your Beauty Requests',[],$locale));
  }
 }
 public function test_invalid_language_cannot_be_saved(): void {
  $this->actingAs(User::factory()->create())->patchJson('/account/settings',['notifications'=>['requests'=>true,'quotations'=>true,'messages'=>true],'language'=>'invalid'])->assertUnprocessable();
  $this->assertSame('en',auth()->user()->fresh()->preferred_language);
 }
}
