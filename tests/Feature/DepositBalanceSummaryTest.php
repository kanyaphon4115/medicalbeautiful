<?php
namespace Tests\Feature;

use App\Models\{Booking,Quotation,TreatmentRequest,User};
use App\Services\DepositPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DepositBalanceSummaryTest extends TestCase
{
 use RefreshDatabase;
 private function booking():Booking {
  $owner=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);
  $item=TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>$owner->id,'name'=>$owner->name,'email'=>$owner->email,'procedure'=>'Eye Surgery',
   'status'=>'pending_booking_deposit','consented_at'=>now(),'consent_text'=>'Test consent','selected_hospital_id'=>$hospital->id,
   'selected_hospital_name'=>$hospital->name,'hospital_approved_at'=>now(),'payment_confirmed_at'=>now()]);
  $quote=Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>'650000.00','details'=>'Treatment details','valid_until'=>today()->addMonth()]);
  $booking=Booking::create(['treatment_request_id'=>$item->id,'quotation_id'=>$quote->id,'hospital_id'=>$hospital->id,'user_id'=>$owner->id,
   'deposit_phase'=>'treatment','deposit_percentage'=>10,'deposit_amount_satang'=>6000000,'deposit_status'=>'Pending','status'=>'Pending Payment','is_test'=>false,'payment_is_simulated'=>false]);
  $this->payment($booking,'initial','approved',500000);
  return $booking;
 }
 private function payment(Booking $booking,string $purpose,string $status,int $amount){
  return $booking->paymentSubmissions()->create(['purpose'=>$purpose,'status'=>$status,'amount_satang'=>$amount,'method'=>'Bank Transfer','submitted_at'=>now(),
   'reviewed_at'=>in_array($status,['approved','Paid'],true)?now():null]);
 }
 public function test_pending_rejected_failed_and_information_requested_payments_do_not_reduce_balance():void {
  foreach(['pending','rejected','failed','information_requested'] as $status){
   $booking=$this->booking();$this->payment($booking,'treatment',$status,6000000);
   $summary=(new DepositPayment)->treatmentSummary($booking);
   $this->assertSame(500000,$summary['initial_satang']);$this->assertSame(0,$summary['booking_paid_satang']);
   $this->assertSame(500000,$summary['total_paid_satang']);$this->assertSame(64500000,$summary['remaining_satang']);
   $this->assertSame(6000000,$summary['outstanding_satang']);
   // A mirrored booking flag cannot override an actual pending/rejected/failed transaction.
   $booking->update(['deposit_status'=>'Paid','payment_verified_at'=>now()]);
   $this->assertSame(64500000,(new DepositPayment)->treatmentSummary($booking)['remaining_satang']);
  }
 }
 public function test_approved_and_paid_rows_count_once_without_adding_booking_amount_again():void {
  foreach(['approved','Paid'] as $status){
   $booking=$this->booking();$this->payment($booking,'treatment',$status,6000000);
   $booking->update(['deposit_status'=>'Paid','payment_verified_at'=>now(),'status'=>'Confirmed']);
   $summary=(new DepositPayment)->treatmentSummary($booking);
   $this->assertSame(6000000,$summary['booking_paid_satang']);$this->assertSame(6500000,$summary['total_paid_satang']);
   $this->assertSame(58500000,$summary['remaining_satang']);$this->assertSame(0,$summary['outstanding_satang']);
   $this->assertSame(58500000,(new DepositPayment)->summary($booking)['remaining_satang']);
  }
 }
 public function test_only_approved_step_four_amount_is_counted_and_overpayment_never_produces_a_negative_balance():void {
  $booking=$this->booking();$booking->paymentSubmissions()->where('purpose','initial')->update(['status'=>'rejected']);
  $this->payment($booking,'initial','pending',500000);$this->payment($booking,'initial','failed',500000);
  $this->assertSame(0,(new DepositPayment)->paidSummary($booking)['total_paid_satang']);
  $this->payment($booking,'treatment','approved',70000000);
  $summary=(new DepositPayment)->treatmentSummary($booking);
  $this->assertSame(70000000,$summary['total_paid_satang']);$this->assertSame(0,$summary['remaining_satang']);
 }
 public function test_legacy_paid_fields_are_used_only_if_the_stage_has_no_transactions():void {
  $booking=$this->booking();$booking->update(['deposit_status'=>'Paid','payment_verified_at'=>now(),'status'=>'Confirmed']);
  $this->assertSame(6500000,(new DepositPayment)->paidSummary($booking)['total_paid_satang']);
  $this->payment($booking,'treatment','approved',6000000);
  $this->assertSame(6500000,(new DepositPayment)->paidSummary($booking)['total_paid_satang']);
 }
 public function test_simulated_payments_are_not_deducted_from_real_hospital_balance():void {
  $booking=$this->booking();$this->payment($booking,'treatment','approved',6000000);
  $booking->update(['is_test'=>true,'payment_is_simulated'=>true,'deposit_status'=>'Paid','payment_verified_at'=>now()]);
  $summary=(new DepositPayment)->paidSummary($booking);
  $this->assertSame(0,$summary['total_paid_satang']);$this->assertSame(65000000,$summary['remaining_satang']);
 }
 public function test_admin_and_customer_show_the_same_pending_and_paid_balances():void {
  $booking=$this->booking();$payment=$this->payment($booking,'treatment','pending',6000000);
  $admin=User::factory()->create(['role'=>'admin']);$owner=$booking->customer;
  foreach([false,true] as $approved){
   if($approved){$payment->update(['status'=>'approved','reviewed_at'=>now()]);$booking->update(['deposit_status'=>'Paid','payment_verified_at'=>now(),'status'=>'Confirmed']);}
   $expectedPaid=$approved?'THB 65,000.00':'THB 5,000.00';$expectedBalance=$approved?'THB 585,000.00':'THB 645,000.00';
   $adminPage=$this->actingAs($admin)->get('/admin/payments?stage=treatment&payment='.$payment->id)->assertOk();
   $customerPage=$this->actingAs($owner)->get('/my-requests')->assertOk();
   $privatePage=$this->get(route('guest.requests.show',$booking->treatmentRequest->reference))->assertOk();
   foreach([$adminPage,$customerPage,$privatePage] as $page){
    $page->assertSee('Booking Deposit Paid')->assertSee('Total Deposit Paid')->assertSee($expectedPaid)->assertSee($expectedBalance)
     ->assertSee('Remaining amount to be paid directly to the hospital or clinic on the treatment date.');
    if(!$approved)$page->assertSee('Amount to Verify (not yet paid)');
   }
   if($approved)$adminPage->assertDontSee('Amount to Verify (not yet paid)');
   $this->getJson(route('bookings.deposit',$booking))->assertOk()->assertJsonPath('total_paid_satang',$approved?6500000:500000)
    ->assertJsonPath('remaining_satang',$approved?58500000:64500000);
  }
  $this->assertDatabaseCount('payment_submissions',2);
 }
}
