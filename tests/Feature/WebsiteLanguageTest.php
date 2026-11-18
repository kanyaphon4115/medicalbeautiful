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
 public function test_admin_language_selection_is_shared_with_customer_pages_and_can_be_switched_back():void {
  $admin=User::factory()->create(['role'=>'admin']);$this->actingAs($admin);
  $this->get('/admin/customers?lang=th')->assertOk()->assertSee('<html lang="th">',false)->assertSessionHas('website_locale','th')->assertSee('ดูบัญชีลูกค้า');
  $this->get('/admin/chat-management')->assertOk()->assertSee('<html lang="th">',false)->assertSee('จัดการแชท');
  $this->get('/patient-reviews')->assertOk()->assertSee('<html lang="th">',false);
  $this->get('/admin/payments?lang=ko')->assertOk()->assertSee('<html lang="ko">',false)->assertSee('name="lang" value="ko"',false)->assertDontSee('data-no-translate lang="en"',false);
  $this->get('/admin/bookings')->assertOk()->assertSee('<html lang="ko">',false);
  $this->get('/admin/customers?lang=en')->assertOk()->assertSee('<html lang="en">',false);
  $this->get('/patient-reviews')->assertSee('<html lang="en">',false);
 }
 public function test_review_validation_errors_use_the_selected_language():void {
  $customer=User::factory()->create();$this->actingAs($customer);
  foreach(['th'=>'คะแนน','ko'=>'평점'] as $locale=>$label){
   $this->get('/patient-reviews?lang='.$locale)->assertOk();
   $this->postJson(route('reviews.store'),['rating'=>7,'privacy_consent'=>1])->assertUnprocessable()->assertJsonValidationErrors('rating')->assertJsonPath('errors.rating.0',fn($text)=>str_contains($text,$label));
  }
 }
}
