<?php

namespace Tests\Feature;

use App\Models\{User,TreatmentRequest,Quotation,Booking};
use App\Notifications\HospitalQuotationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationWithdrawalTest extends TestCase
{
 use RefreshDatabase;
 protected function setUp(): void {
  parent::setUp();Notification::fake();
  $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
 }
 private function fixture(): array {
  $owner=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);
  $item=TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>$owner->id,'name'=>'Customer','email'=>$owner->email,'procedure'=>'Rhinoplasty','status'=>'quotes_received','consented_at'=>now(),'consent_text'=>'Consent']);
  $quote=Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>70000,'details'=>'UNIQUE-QUOTATION','valid_until'=>today()->addDays(5)]);
  return [$owner,$hospital,$item,$quote];
 }
 public function test_hospital_can_withdraw_and_customer_cannot_accept_stale_offer(): void {
  [$owner,$hospital,$item,$quote]=$this->fixture();
  $this->actingAs($hospital)->get(route('hospital.dashboard'))->assertOk()->assertSee('Withdraw Quotation');
  $this->post(route('hospital.quotations.withdraw',$quote))->assertRedirect();
  $this->post(route('hospital.quotations.withdraw',$quote))->assertRedirect();
  $this->assertNotNull($quote->fresh()->withdrawn_at);
  $this->assertSame('pending',$item->fresh()->status);
  $this->assertCount(0,$item->fresh()->quotations);
  $this->get(route('hospital.dashboard'))->assertSee('Withdrawn');
  $this->actingAs($owner)->get('/my-requests')->assertDontSee('UNIQUE-QUOTATION');
  $this->post(route('quotations.accept',$quote))->assertNotFound();
  $this->withSession(['real_guest.'.$item->reference=>now()->addHour()->timestamp])
   ->get(route('guest.requests.show',$item->reference))->assertOk()->assertDontSee('UNIQUE-QUOTATION');
  $this->post(route('guest.requests.accept',[$item->reference,$quote]),['expected_total_satang'=>7000000])->assertNotFound();
  $this->assertDatabaseCount('bookings',0);$this->assertDatabaseCount('quotations',1);
 }
 public function test_only_owning_hospital_can_withdraw_and_booked_offers_are_protected(): void {
  [$owner,$hospital,$item,$quote]=$this->fixture();
  $this->actingAs($owner)->post(route('hospital.quotations.withdraw',$quote))->assertForbidden();
  $this->actingAs(User::factory()->create(['role'=>'hospital']))->post(route('hospital.quotations.withdraw',$quote))->assertNotFound();
  Booking::create(['treatment_request_id'=>$item->id,'quotation_id'=>$quote->id,'hospital_id'=>$hospital->id,'user_id'=>$owner->id,'status'=>'Pending Payment','deposit_status'=>'Pending']);
  $this->actingAs($hospital)->post(route('hospital.quotations.withdraw',$quote))->assertUnprocessable();
  $this->assertNull($quote->fresh()->withdrawn_at);
 }
 public function test_withdrawal_keeps_other_hospital_offer_and_republishing_sends_new_notification(): void {
  [$owner,$hospital,$item,$quote]=$this->fixture();
  $other=User::factory()->create(['role'=>'hospital']);
  Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$other->id,'amount'=>60000,'details'=>'Other offer','valid_until'=>today()->addDays(5)]);
  $data=['amount'=>70000,'details'=>'UNIQUE-QUOTATION','valid_until'=>today()->addDays(5)->format('Y-m-d')];
  $this->actingAs($hospital)->post(route('hospital.quote',$item),$data)->assertRedirect();
  $this->post(route('hospital.quotations.withdraw',$quote))->assertRedirect();
  $this->assertSame('quotes_received',$item->fresh()->status);
  $this->assertCount(1,$item->fresh()->quotations);
  $this->post(route('hospital.quote',$item),$data)->assertRedirect();
  $this->assertNull($quote->fresh()->withdrawn_at);
  $this->assertDatabaseCount('quotations',2);
  Notification::assertSentOnDemandTimes(HospitalQuotationReceived::class,2);
 }
}
