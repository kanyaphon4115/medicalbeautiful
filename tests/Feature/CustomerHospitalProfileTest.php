<?php
namespace Tests\Feature;
use App\Models\{User,HospitalProfile,Quotation,TreatmentRequest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CustomerHospitalProfileTest extends TestCase {
 use RefreshDatabase;
 public function test_customer_sees_real_quoted_hospital_profile_without_private_verification_data(){
  $customer=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital','name'=>'Account fallback']);
  $item=TreatmentRequest::create(['reference'=>(string)\Illuminate\Support\Str::uuid(),'user_id'=>$customer->id,'name'=>$customer->name,'email'=>$customer->email,'procedure'=>'Rhinoplasty','goals'=>'Treatment goal','status'=>'quotes_received','consented_at'=>now(),'consent_text'=>'Consent']);
  Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>60000,'valid_until'=>today()->addDays(7),'details'=>'Actual offer']);
  HospitalProfile::create(['hospital_id'=>$hospital->id,'name'=>'Actual Profile Hospital','short_description'=>'Real specialty overview','address'=>'Actual street','phone'=>'021234567','email'=>'contact@hospital.test','registered_name'=>'SECRET REGISTERED NAME','license_number'=>'SECRET LICENSE']);
  $response=$this->actingAs($customer)->get('/my-requests')->assertOk()->assertSee('Actual Profile Hospital')->assertSee('Actual street')->assertSee('021234567')->assertDontSee('SECRET REGISTERED NAME')->assertDontSee('SECRET LICENSE');
  $this->actingAs(User::factory()->create())->get('/my-requests')->assertOk()->assertDontSee('Actual Profile Hospital')->assertDontSee('Actual street');
 }
 public function test_hospital_account_name_is_used_when_no_profile_exists(){
  $customer=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital','name'=>'Original Hospital Name']);
  $item=TreatmentRequest::create(['reference'=>(string)\Illuminate\Support\Str::uuid(),'user_id'=>$customer->id,'name'=>$customer->name,'email'=>$customer->email,'procedure'=>'Rhinoplasty','goals'=>'Treatment goal','status'=>'quotes_received','consented_at'=>now(),'consent_text'=>'Consent']);
  Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>60000,'valid_until'=>today()->addDays(7),'details'=>'Actual offer']);
  $this->actingAs($customer)->get('/my-requests')->assertOk()->assertSee('Original Hospital Name');
 }
}


