<?php
namespace Tests\Feature;
use App\Models\{User,TreatmentRequest,Quotation,Booking};
use App\Services\DepositPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class SeparateBookingDepositTest extends TestCase {
 use RefreshDatabase;
 public function test_separate_deposit_preserves_initial_payment_until_verified_and_matches_dashboards():void {
  $owner=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);$admin=User::factory()->create(['role'=>'admin']);
  $item=TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>$owner->id,'name'=>$owner->name,'email'=>$owner->email,'procedure'=>'Eye Surgery','status'=>'awaiting_hospital_confirmation','selected_hospital_id'=>$hospital->id,'selected_hospital_name'=>$hospital->name,'hospital_approved_at'=>now(),'payment_confirmed_at'=>now(),'consented_at'=>now(),'consent_text'=>'Consent']);
  $quote=Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>'63000.00','details'=>'Treatment details','valid_until'=>today()->addMonth()]);
  $booking=Booking::create(['user_id'=>$owner->id,'hospital_id'=>$hospital->id,'quotation_id'=>$quote->id,'treatment_request_id'=>$item->id,'booking_deposit_is_additional'=>true,'deposit_percentage'=>10,'deposit_amount_satang'=>450000,'deposit_status'=>'Paid','payment_verified_at'=>now(),'status'=>'Awaiting Hospital Confirmation']);
  $initial=$booking->paymentSubmissions()->create(['purpose'=>'initial','status'=>'approved','amount_satang'=>450000,'method'=>'Bank Transfer','submitted_at'=>now(),'reviewed_at'=>now(),'reviewer_id'=>$admin->id]);$original=$initial->fresh()->getRawOriginal();
  $summary=(new DepositPayment)->treatmentSummary($booking);
  foreach(['total_satang'=>6300000,'initial_satang'=>450000,'required_satang'=>630000,'booking_paid_satang'=>0,'additional_satang'=>630000,'outstanding_satang'=>630000,'total_paid_satang'=>450000,'remaining_satang'=>5850000,'total_after_booking_satang'=>1080000,'remaining_after_booking_satang'=>5220000] as $key=>$expected)$this->assertSame($expected,$summary[$key],$key);
  $this->actingAs($owner)->get('/my-requests')->assertOk()->assertViewHas('requests',fn($rows)=>$rows->first()['treatment_deposit']['outstanding_satang']===630000);
  $this->get(route('guest.requests.show',$item->reference))->assertOk()->assertSee('THB 58,500.00')->assertDontSee('Remaining Balance After Step 9 Payment')->assertDontSee('Additional Booking Deposit Required');
  $this->actingAs($admin)->get('/admin/payments?stage=initial&payment='.$initial->id)->assertOk()->assertSee('THB 63,000.00')->assertSee('THB 58,500.00')->assertDontSee('Remaining Balance After Step 9 Payment')->assertDontSee('Additional Booking Deposit Required');
  $this->get(route('admin.bookings',['record'=>$booking->id]))->assertOk()->assertSee('THB 58,500.00')->assertDontSee('Remaining Balance After Step 9 Payment')->assertDontSee('Additional Booking Deposit Required');
  $this->actingAs($hospital)->get(route('hospital.dashboard'))->assertOk()->assertSee('THB 58,500.00')->assertDontSee('Remaining Balance After Step 9 Payment')->assertDontSee('Additional Booking Deposit Required');
  $this->actingAs($owner)->postJson(route('bookings.treatment-deposit',$booking),['confirm_treatment'=>true])->assertOk()->assertJsonPath('deposit_satang',630000);
  $this->getJson(route('chat.access'))->assertOk()->assertJsonPath('hospital_requests.0.unlocked',false);
  $pending=$booking->paymentSubmissions()->where('purpose','treatment')->firstOrFail();
  $this->assertSame(5850000,(new DepositPayment)->treatmentSummary($booking->fresh())['remaining_satang']);
  $this->post(route('guest.requests.payment-claim',$item->reference),['transaction_reference'=>'STEP9-SEPARATE'])->assertRedirect();
  $this->actingAs($admin)->post(route('admin.payments.review',$pending),['decision'=>'approved','confirmation'=>1,'funds_verified'=>1,'notes'=>'Verified funds','verification_reference'=>'STEP9-SEPARATE'])->assertRedirect();
  $summary=(new DepositPayment)->treatmentSummary($booking->fresh());
  $this->assertSame(630000,$summary['booking_paid_satang']);$this->assertSame(1080000,$summary['total_paid_satang']);$this->assertSame(5220000,$summary['remaining_satang']);$this->assertSame(0,$summary['outstanding_satang']);
  $this->assertSame($original,$initial->fresh()->getRawOriginal());
  $this->get('/admin/payments?stage=treatment&payment='.$pending->id)->assertOk()->assertSee('THB 10,800.00')->assertSee('THB 52,200.00');
  $this->actingAs($owner)->getJson(route('chat.access'))->assertOk()->assertJsonPath('hospital_requests.0.booking_confirmed',true)->assertJsonPath('hospital_requests.0.step9_approved',true)->assertJsonPath('hospital_requests.0.unlocked',true);
  $this->postJson('/chat/messages',['channel'=>'hospital','treatment_request_id'=>$item->id,'body'=>'Confirmed booking message'])->assertCreated();
  $this->actingAs($hospital)->getJson(route('chat.bookings.messages',$booking))->assertOk()->assertJsonCount(1,'messages');
  $this->postJson(route('chat.bookings.store',$booking),['body'=>'Hospital reply'])->assertCreated();
  $this->actingAs($owner)->get('/my-requests')->assertOk()->assertViewHas('requests',fn($rows)=>$rows->first()['treatment_deposit']['remaining_satang']===5220000);
  $this->get(route('guest.requests.show',$item->reference))->assertOk()->assertSee('THB 10,800.00')->assertSee('THB 52,200.00');
  $this->actingAs($hospital)->get(route('hospital.dashboard'))->assertOk()->assertSee('THB 10,800.00')->assertSee('THB 52,200.00');
 }
}
