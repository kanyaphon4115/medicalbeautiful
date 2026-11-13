<?php
namespace Tests\Feature;
use App\Models\{User,TreatmentRequest,Quotation,Booking,PaymentSubmission};
use App\Services\DepositPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class TreatmentDepositWorkflowTest extends TestCase {
 use RefreshDatabase;
 private function booking(int $percentage=10):Booking {
  $owner=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);
  $hospital->forceFill(['treatment_deposit_percentage'=>$percentage])->save();
  $item=TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>$owner->id,'name'=>$owner->name,'email'=>$owner->email,'procedure'=>'Eye Surgery','status'=>'awaiting_hospital_confirmation','selected_hospital_id'=>$hospital->id,'selected_hospital_name'=>$hospital->name,'hospital_approved_at'=>now(),'payment_confirmed_at'=>now(),'consented_at'=>now(),'consent_text'=>'Consent']);
  $quote=Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>'650000.00','details'=>'Treatment details','valid_until'=>today()->addDays(30)]);
  return Booking::create(['user_id'=>$owner->id,'hospital_id'=>$hospital->id,'quotation_id'=>$quote->id,'treatment_request_id'=>$item->id,'deposit_amount_satang'=>500000,'deposit_status'=>'Paid','payment_verified_at'=>now(),'status'=>'Awaiting Hospital Confirmation']);
 }
 public function test_percentage_and_initial_deduction_use_integer_money():void {
  foreach([10=>6000000,15=>9250000,20=>12500000] as $percentage=>$expected){
   $booking=$this->booking($percentage);$summary=(new DepositPayment)->treatmentSummary($booking);
   $this->assertSame($expected,$summary['additional_satang']);$this->assertSame(500000,$summary['initial_satang']);
  }
 }
 public function test_initial_payment_does_not_confirm_and_step_nine_approval_does():void {
  $booking=$this->booking();$this->actingAs($booking->customer);
  $this->assertSame('account_activated',$booking->treatmentRequest->customer_status);
  $this->postJson(route('bookings.treatment-deposit',$booking),['confirm_treatment'=>true])->assertOk()->assertJsonPath('deposit_satang',6000000);
  $this->postJson(route('bookings.treatment-deposit',$booking),['confirm_treatment'=>true])->assertOk()->assertJsonPath('deposit_satang',6000000);
  $this->assertDatabaseCount('bookings',1);$this->assertDatabaseCount('payment_submissions',2);
  $this->get('/my-requests')->assertOk()->assertSee('Account Activated')->assertSee('Pending Booking Deposit')->assertSee('Treatment Deposit')->assertDontSee('Booking &amp; Payment Status',false)->assertViewHas('requests',fn($rows)=>$rows->first()['status']==='pending_booking_deposit' && $rows->first()['treatment_deposit']['initial_satang']===500000);
  $item=$booking->treatmentRequest;
  $this->post(route('guest.requests.payment-claim',$item->reference),['transaction_reference'=>'STEP9-TEST'])->assertRedirect();
  $this->post(route('guest.requests.payment-claim',$item->reference),['transaction_reference'=>'STEP9-TEST'])->assertRedirect();
  $this->assertDatabaseCount('payment_submissions',2);
  $submission=PaymentSubmission::where('purpose','treatment')->firstOrFail();
  $this->assertSame(6000000,$submission->amount_satang);$this->assertSame('Pending Verification',$booking->fresh()->deposit_status);
  $this->assertSame('Pending Payment',$booking->fresh()->status);
  $this->postJson(route('admin.payments.review',$submission),[])->assertForbidden();
  $admin=User::factory()->create(['role'=>'admin']);$this->actingAs($admin);
  $data=['decision'=>'approved','confirmation'=>1,'funds_verified'=>1,'notes'=>'Verified funds','verification_reference'=>'STEP9-BANK-TEST'];
  $this->post(route('admin.payments.review',$submission),$data)->assertRedirect();
  $this->assertSame('Confirmed',$booking->fresh()->status);$this->assertSame('confirmed',$item->fresh()->customer_status);
  $data['verification_reference']='STEP9-DUPLICATE';$this->postJson(route('admin.payments.review',$submission),$data)->assertConflict();
  $this->actingAs($booking->hospital)->post(route('hospital.complete',$booking))->assertRedirect();
  $this->assertSame('completed',$item->fresh()->customer_status);
 }
 public function test_all_filters_count_database_requests_by_current_stage():void {
  $owner=User::factory()->create();
  foreach(['pending','quotes_received','account_activated','pending_booking_deposit','confirmed','completed','cancelled'] as $status){
   $booking=$this->booking();$item=$booking->treatmentRequest;
   $item->update(['user_id'=>$owner->id]);$booking->update(['user_id'=>$owner->id]);
   if($status==='pending')$booking->update(['deposit_status'=>'Pending','payment_verified_at'=>null]);
   if($status==='quotes_received'){$booking->delete();$item->update(['status'=>'quotes_received','selected_hospital_id'=>null,'selected_hospital_name'=>null,'hospital_approved_at'=>null,'payment_confirmed_at'=>null]);}
   if($status==='pending_booking_deposit')$booking->update(['deposit_phase'=>'treatment','deposit_status'=>'Pending','payment_verified_at'=>null,'status'=>'Pending Payment']);
   if(in_array($status,['confirmed','completed']))$booking->update(['deposit_phase'=>'treatment','status'=>$status==='completed'?'Completed':'Confirmed']);
   if($status==='cancelled')$item->update(['status'=>'cancelled']);
   $this->assertSame($status,$item->fresh()->customer_status);
  }
  $response=$this->actingAs($owner)->get('/my-requests')->assertOk()->assertSee('data-filter-count="all">7</span>',false);
  foreach(['pending','quotes_received','account_activated','pending_booking_deposit','confirmed','completed','cancelled'] as $status)$response->assertSee('data-filter-count="'.$status.'">1</span>',false);
 }
 public function test_hospital_policy_is_admin_only_and_snapshotted():void {
  $booking=$this->booking();$hospital=$booking->hospital;
  $this->actingAs($booking->customer)->postJson(route('admin.hospitals.deposit-policy',$hospital),['treatment_deposit_percentage'=>20])->assertForbidden();
  $admin=User::factory()->create(['role'=>'admin']);$this->actingAs($admin);
  $this->postJson(route('admin.hospitals.deposit-policy',$hospital),['treatment_deposit_percentage'=>21])->assertUnprocessable();
  $this->post(route('admin.hospitals.deposit-policy',$hospital),['treatment_deposit_percentage'=>20])->assertRedirect();
  $this->actingAs($booking->customer)->postJson(route('bookings.treatment-deposit',$booking),['confirm_treatment'=>1])->assertOk()->assertJsonPath('deposit_satang',12500000);
  $hospital->forceFill(['treatment_deposit_percentage'=>10])->save();
  $this->assertSame(20,(new DepositPayment)->treatmentSummary($booking->fresh())['percentage']);
 }
 public function test_owner_and_verified_initial_payment_are_required():void {
  $booking=$this->booking();$this->actingAs(User::factory()->create())->postJson(route('bookings.treatment-deposit',$booking),['confirm_treatment'=>1])->assertNotFound();
  $booking->update(['deposit_status'=>'Pending','payment_verified_at'=>null]);
  $this->actingAs($booking->customer)->postJson(route('bookings.treatment-deposit',$booking),['confirm_treatment'=>1])->assertUnprocessable();
  $this->assertDatabaseCount('payment_submissions',0);
 }
}
