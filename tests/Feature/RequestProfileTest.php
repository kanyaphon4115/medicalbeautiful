<?php

namespace Tests\Feature;

use App\Models\{User,TreatmentRequest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RequestProfileTest extends TestCase
{
 use RefreshDatabase;

 public function test_birth_date_determines_age_and_gender_has_only_two_allowed_values(): void
 {
  $this->travelTo(\Carbon\Carbon::parse('2026-03-14 12:00:00'));
  $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
  $owner=User::factory()->create();
  $data=['name'=>'Jane','email'=>$owner->email,'date_of_birth'=>'1995-03-15','age'=>99,'gender'=>'Female','procedures'=>['Rhinoplasty'],'consent_to_share'=>true];
  $this->actingAs($owner)->postJson('/requests',$data)->assertCreated();
  $this->assertSame(30,TreatmentRequest::latest('id')->firstOrFail()->age);
  $this->travelTo(\Carbon\Carbon::parse('2026-03-15 12:00:00'));
  $data['gender']='Male';
  $this->postJson('/requests',$data)->assertCreated();
  $this->assertSame(31,TreatmentRequest::latest('id')->firstOrFail()->age);
  foreach(['Other','Non-binary','Prefer not to say'] as $gender){
   $data['gender']=$gender;
   $this->postJson('/requests',$data)->assertUnprocessable()->assertJsonValidationErrors('gender');
   $this->postJson(route('guest.requests.store'),$data+['submission_reference'=>(string)\Illuminate\Support\Str::uuid(),'goals'=>'Goals','preferred_surgery_month'=>12,'preferred_surgery_year'=>2026])->assertUnprocessable()->assertJsonValidationErrors('gender');
  }
  $this->travelBack();
 }

 public function test_saved_personal_details_are_reused_without_previous_treatment_details(): void
 {
  $owner=User::factory()->create();
  $this->actingAs($owner)->postJson('/requests',[
   'name'=>'Jane Kim','email'=>$owner->email,'line_id'=>'janekim',
   'phone_number'=>'+66 81 234 5678','date_of_birth'=>'1995-03-15','gender'=>'Female',
   'procedures'=>['Rhinoplasty'],'goals'=>'Do not copy this goal',
   'medical_information'=>'Do not copy this medical information','consent_to_share'=>true,
  ])->assertCreated();
  $this->getJson(route('requests.profile'))->assertOk()
   ->assertHeader('Cache-Control','no-store, private')
   ->assertExactJson(['profile'=>[
    'name'=>'Jane Kim','email'=>$owner->email,'line_id'=>'janekim',
    'phone_number'=>'+66812345678','date_of_birth'=>'1995-03-15','gender'=>'Female',
   ]]);
  $this->assertSame('1995-03-15',TreatmentRequest::firstOrFail()->date_of_birth);
  $this->assertNotSame('1995-03-15',DB::table('users')->where('id',$owner->id)->value('date_of_birth'));
  $this->actingAs(User::factory()->create())->getJson(route('requests.profile'))->assertOk()->assertJsonPath('profile.line_id','');
 }

 public function test_profile_requires_login_and_invalid_details_are_not_saved(): void
 {
  $this->getJson(route('requests.profile'))->assertUnauthorized();
  $owner=User::factory()->create();
  $this->actingAs($owner)->postJson('/requests',[
   'name'=>'Changed','email'=>$owner->email,'date_of_birth'=>now()->addYear()->format('Y-m-d'),
   'procedures'=>['Rhinoplasty'],'consent_to_share'=>true,
  ])->assertUnprocessable()->assertJsonValidationErrors('date_of_birth');
  $this->assertSame($owner->name,$owner->fresh()->name);
  $this->assertDatabaseCount('treatment_requests',0);
 }
}
