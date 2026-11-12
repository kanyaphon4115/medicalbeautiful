<?php
namespace Tests\Feature;
use App\Models\{User,TreatmentRequest,Quotation,Booking};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class DepositPaymentTest extends TestCase {
 use RefreshDatabase;
 private function offer(): Quotation {
  $owner=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital','name'=>'Hidden Payment Hospital']);
  $item=TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>$owner->id,'name'=>$owner->name,'email'=>$owner->email,'procedure'=>'Eye Surgery','status'=>'quotes_received','consented_at'=>now(),'consent_text'=>'Consent']);
  return Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>50000,'details'=>'Existing treatment details','valid_until'=>today()->addDays(5)]);
 }
 public function test_accept_returns_server_calculated_pending_deposit_without_real_qr(): void {
  $quote=$this->offer();$owner=User::findOrFail($quote->treatmentRequest->user_id);
  $result=$this->actingAs($owner)->postJson(route('quotations.accept',$quote),['expected_total_satang'=>5000000,'deposit_status'=>'Paid','status'=>'Confirmed','payment_status'=>'Paid']);
  $result->assertCreated()->assertJsonPath('total_satang',5000000)->assertJsonPath('deposit_satang',500000)->assertJsonPath('remaining_satang',5000000)->assertJsonPath('total_paid_satang',0)
   ->assertJsonPath('payment_status','Pending Payment')->assertJsonPath('booking_status','Pending Payment')->assertJsonPath('integration.available',false)->assertJsonPath('integration.qr_payload',null)->assertDontSee('Hidden Payment Hospital');
  $booking=Booking::firstOrFail();$this->assertSame('Pending',$booking->deposit_status);$this->assertSame('Pending Payment',$booking->status);
  $this->assertNull($quote->treatmentRequest->fresh()->payment_confirmed_at);
  $this->getJson(route('bookings.deposit',$booking))->assertOk()->assertJsonPath('booking_status','Pending Payment');
  $this->assertSame('Pending',$booking->fresh()->deposit_status);
  $this->get('/my-requests')->assertOk()->assertSee('payment_url')->assertViewHas('requests',fn($rows)=>$rows->first()['status']==='pending');
  $this->assertSame('pending_payment',$quote->treatmentRequest->fresh()->status);
  $booking->update(['deposit_phase'=>'treatment','deposit_status'=>'Paid','status'=>'Confirmed','payment_verified_at'=>now()]);
  $this->get('/my-requests')->assertViewHas('requests',fn($rows)=>$rows->first()['status']==='confirmed');
  $booking->update(['status'=>'Completed']);
  $this->get('/my-requests')->assertViewHas('requests',fn($rows)=>$rows->first()['status']==='completed');
  $this->actingAs(User::factory()->create())->getJson(route('bookings.deposit',$booking))->assertNotFound();
  $this->actingAs($quote->hospital)->getJson(route('bookings.deposit',$booking))->assertNotFound();
 }
 public function test_changed_price_or_wrong_owner_cannot_accept_offer(): void {
  $quote=$this->offer();
  $this->actingAs(User::factory()->create())->postJson(route('quotations.accept',$quote))->assertNotFound();
  $this->actingAs(User::findOrFail($quote->treatmentRequest->user_id))->postJson(route('quotations.accept',$quote),['expected_total_satang'=>100])->assertUnprocessable();
  $this->assertDatabaseCount('bookings',0);$this->assertNull($quote->treatmentRequest->fresh()->selected_hospital_id);
 }
 public function test_explicitly_reset_unpaid_booking_can_be_reaccepted_without_losing_its_record(): void {
  $quote=$this->offer();$owner=User::findOrFail($quote->treatmentRequest->user_id);
  $booking=Booking::create(['treatment_request_id'=>$quote->treatment_request_id,'quotation_id'=>$quote->id,'hospital_id'=>$quote->hospital_id,'user_id'=>$owner->id,'status'=>'Awaiting Acceptance','deposit_status'=>'Pending']);
  $created=$booking->created_at->toDateTimeString();
  $rows=$this->actingAs($owner)->get('/my-requests')->assertOk()->viewData('requests');
  $offer=$rows->first()['hospital_responses']->first();
  $this->assertNull($offer['payment_url']);$this->assertNotNull($offer['accept_url']);
  $this->getJson(route('bookings.deposit',$booking))->assertNotFound();
  $this->postJson(route('quotations.accept',$quote),['expected_total_satang'=>5000000])->assertCreated()->assertJsonPath('booking_status','Pending Payment');
  $this->assertDatabaseCount('bookings',1);$this->assertSame($booking->id,Booking::first()->id);
  $this->assertSame($created,$booking->fresh()->created_at->toDateTimeString());
  $this->assertSame('50000.00',$quote->fresh()->amount);
  $this->postJson(route('quotations.accept',$quote))->assertUnprocessable();
 }
 public function test_reset_marker_cannot_override_a_paid_booking(): void {
  $quote=$this->offer();$owner=User::findOrFail($quote->treatmentRequest->user_id);
  $booking=Booking::create(['treatment_request_id'=>$quote->treatment_request_id,'quotation_id'=>$quote->id,'hospital_id'=>$quote->hospital_id,'user_id'=>$owner->id,'status'=>'Awaiting Acceptance','deposit_status'=>'Paid']);
  $this->actingAs($owner)->postJson(route('quotations.accept',$quote))->assertUnprocessable();
  $this->assertSame('Paid',$booking->fresh()->deposit_status);
 }
}
