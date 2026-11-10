<?php
namespace Tests\Feature;
use App\Models\{User,TreatmentRequest,Quotation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class CustomerQuotationPrivacyTest extends TestCase {
 use RefreshDatabase;
 public function test_customer_receives_real_prices_and_details_without_hospital_identity(): void {
  $owner=User::factory()->create();$other=User::factory()->create();
  $h1=User::factory()->create(['role'=>'hospital','name'=>'Private Hospital Alpha']);$h2=User::factory()->create(['role'=>'hospital','name'=>'Private Hospital Beta']);
  $item=TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>$owner->id,'name'=>$owner->name,'email'=>$owner->email,'procedure'=>'Eye Surgery','status'=>'quotes_received','consented_at'=>now(),'consent_text'=>'Consent']);
  foreach([[$h1,72500.25,'Actual consultation and treatment inclusions'],[$h2,81000,'Second treatment plan']] as [$hospital,$amount,$details]) Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>$amount,'details'=>$details,'valid_until'=>today()->addDays(10)]);
  $response=$this->actingAs($owner)->get('/my-requests')->assertOk()->assertDontSee('Private Hospital Alpha')->assertDontSee('Private Hospital Beta')->assertDontSee('hospital_name')->assertDontSee('hospital_image')->assertDontSee('data-response-image',false)->assertDontSee('data-response-name',false);
  $rows=$response->viewData('requests');$offers=$rows->first()['hospital_responses'];
  $this->assertCount(2,$offers);$this->assertSame(72500.25,$offers[0]['quotation_amount']);$this->assertSame('Actual consultation and treatment inclusions',$offers[0]['details']);
  $this->assertSame('quote_received',$offers[0]['response_status']);$this->assertNotEmpty($offers[0]['valid_until']);
  $this->assertSame(2,$rows->first()['hospitals_contacted']);
  $this->actingAs($other)->get('/my-requests')->assertOk()->assertDontSee('Actual consultation and treatment inclusions')->assertViewHas('requests',fn($requests)=>$requests->isEmpty());
 }
 public function test_pending_request_contains_no_quotation_cards_data(): void {
  $owner=User::factory()->create();TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>$owner->id,'name'=>$owner->name,'email'=>$owner->email,'procedure'=>'Rhinoplasty','status'=>'pending','consented_at'=>now(),'consent_text'=>'Consent']);
  $this->actingAs($owner)->get('/my-requests')->assertOk()->assertSee('No hospital has responded yet.')->assertViewHas('requests',fn($rows)=>$rows->first()['hospital_responses']->isEmpty());
 }
}
