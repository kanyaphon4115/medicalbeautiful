<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ProfilePersistenceTest extends TestCase {
 use RefreshDatabase;
 public function test_profile_save_requires_authentication(): void {$this->patchJson('/account/profile',['name'=>'Updated'])->assertUnauthorized();}
 public function test_profile_changes_persist_without_changing_email_or_roles(): void {
  $user=User::factory()->create();$other=User::factory()->create();
  $this->actingAs($user)->patchJson('/account/profile',['name'=>'Updated Name','phone'=>'+66 81 234 5678','country'=>'TH','language'=>'ko','email'=>'hijack@example.com','role'=>'admin','user_id'=>$other->id,'google_id'=>'not-allowed'])->assertOk()->assertJsonPath('profile.phone','+66812345678')->assertJsonPath('profile.email',$user->email)->assertSessionHas('website_locale','ko');
  $saved=$user->fresh();$this->assertSame('Updated Name',$saved->name);$this->assertSame('+66812345678',$saved->phone_number);$this->assertSame('TH',$saved->country_code);$this->assertSame('ko',$saved->preferred_language);$this->assertSame($user->email,$saved->email);$this->assertSame('user',$saved->role);$this->assertNull($saved->google_id);$this->assertNotSame('Updated Name',$other->fresh()->name);
  $this->get('/')->assertOk()->assertSee('Updated Name')->assertSee('+66812345678')->assertSee('<html lang="ko">',false);
 }
 public function test_invalid_phone_country_and_language_do_not_modify_profile(): void {
  $user=User::factory()->create();$this->actingAs($user);
  foreach(['0812345678','+123','+1234567890123456','+66 abc',['unexpected']] as $phone){$this->patchJson('/account/profile',['name'=>'Should Not Save','phone'=>$phone,'country'=>'TH','language'=>'en'])->assertUnprocessable()->assertJsonValidationErrors('phone');}
  $this->patchJson('/account/profile',['name'=>'Should Not Save','phone'=>'','country'=>'XX','language'=>'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['country','language']);$this->assertSame($user->name,$user->fresh()->name);$this->assertNull($user->fresh()->phone_number);
 }
 public function test_optional_fields_can_be_cleared(): void {
  $user=User::factory()->create();$user->phone_number='+12025550123';$user->country_code='US';$user->save();
  $this->actingAs($user)->patchJson('/account/profile',['name'=>'Updated','phone'=>'','country'=>'','language'=>'en'])->assertOk();$this->assertNull($user->fresh()->phone_number);$this->assertNull($user->fresh()->country_code);
 }

 public function test_request_profile_edit_saves_personal_fields_and_preserves_request_snapshot(): void {
  $user=\App\Models\User::factory()->create();
  $this->actingAs($user)->postJson('/requests',['name'=>'Original Customer','email'=>$user->email,'procedures'=>['Eye Surgery'],'goals'=>'Original goals','consent_to_share'=>true])->assertCreated();
  $request=\App\Models\TreatmentRequest::firstOrFail();
  $this->patchJson('/account/profile',['name'=>'Updated Customer','phone'=>'+66812345678','line_id'=>'new-line','date_of_birth'=>'1995-03-15','gender'=>'Female','language'=>'en','email'=>'changed@example.test'])->assertOk();
  $this->getJson('/requests/profile')->assertOk()->assertJsonPath('profile.name','Updated Customer')->assertJsonPath('profile.line_id','new-line')->assertJsonPath('profile.date_of_birth','1995-03-15')->assertJsonPath('profile.gender','Female')->assertJsonPath('profile.email',$user->email);
  $this->assertSame('Original Customer',$request->fresh()->name);$this->assertSame('Original goals',$request->fresh()->goals);
  $this->postJson('/requests',['name'=>'Updated Customer','email'=>$user->email,'procedures'=>['Rhinoplasty'],'consent_to_share'=>true])->assertCreated();
  $this->assertSame(2,\App\Models\TreatmentRequest::where('user_id',$user->id)->count());
 }
}
