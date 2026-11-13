<?php
namespace Tests\Feature;

use App\Models\{Booking,PaymentSubmission,Quotation,TreatmentRequest,User};
use App\Notifications\{GuestJourneyCode,BookingDepositApproved};
use App\Services\{PaymentLedger,BookingDepositEmailNotification};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPaymentStagesTest extends TestCase
{
 use RefreshDatabase;
 private function fixture(bool $guest=false):array {
  Notification::fake();
  $owner=$guest?null:User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);
  $admin=User::factory()->create(['role'=>'admin']);
  $item=TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>$owner?->id,'name'=>'Stage Payment Customer','email'=>$owner?->email??'stage-guest@example.test','procedure'=>'Eye Surgery',
   'status'=>'pending_payment','selected_hospital_id'=>$hospital->id,'selected_hospital_name'=>$hospital->name,'hospital_approved_at'=>now(),'consented_at'=>now(),'consent_text'=>'Test consent']);
  $quote=Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>'650000.00','details'=>'Existing treatment details','valid_until'=>today()->addMonth()]);
  $booking=Booking::create(['treatment_request_id'=>$item->id,'quotation_id'=>$quote->id,'hospital_id'=>$hospital->id,'user_id'=>$owner?->id,'status'=>'Pending Payment','deposit_status'=>'Pending','deposit_amount_satang'=>500000,'is_test'=>false,'payment_is_simulated'=>false]);
  $payment=(new PaymentLedger)->ensureCurrent($booking);
  return [$booking,$payment,$admin,$owner];
 }
 private function confirm(PaymentSubmission $payment){
  return $this->postJson(route('admin.payments.confirm-transaction',$payment),['confirmation'=>1,'funds_verified'=>1]);
 }
 private function startStepNine(Booking $booking,User $owner):PaymentSubmission {
  $this->actingAs($owner)->postJson(route('bookings.treatment-deposit',$booking),['confirm_treatment'=>1])->assertOk()->assertJsonPath('deposit_satang',6000000);
  return $booking->paymentSubmissions()->where('purpose','treatment')->firstOrFail();
 }
 public function test_step_four_confirmation_sends_otp_and_never_creates_step_nine():void {
  [$booking,$payment,$admin]=$this->fixture(true);$this->actingAs($admin);
  $this->get('/admin/payments?stage=initial')->assertOk()->assertSee('Initial Deposit Amount')->assertSee('Amount to Verify')->assertDontSee('Deposit Summary')->assertDontSee('Save Deposit Percentage');
  $this->confirm($payment)->assertRedirect();
  $this->assertSame('approved',$payment->fresh()->status);$this->assertSame('Awaiting Hospital Confirmation',$booking->fresh()->status);
  $this->assertDatabaseCount('payment_submissions',1);$this->assertDatabaseMissing('payment_submissions',['purpose'=>'treatment']);
  Notification::assertSentOnDemandTimes(GuestJourneyCode::class,1);
  $this->confirm($payment)->assertConflict();$this->assertDatabaseCount('payment_reviews',1);
  Notification::assertSentOnDemandTimes(GuestJourneyCode::class,1);
 }
 public function test_each_stage_has_its_own_id_and_only_selected_payment_can_be_confirmed():void {
  [$booking,$initial,$admin,$owner]=$this->fixture();$this->actingAs($admin);$this->confirm($initial)->assertRedirect();
  $original=$initial->fresh()->getRawOriginal();$confirmedAt=$booking->treatmentRequest->fresh()->payment_confirmed_at;
  $treatment=$this->startStepNine($booking,$owner);
  $this->assertNotSame($initial->reference,$treatment->reference);$this->assertSame(6000000,$treatment->amount_satang);
  $this->actingAs($admin);$this->confirm($initial)->assertConflict();
  $this->postJson(route('admin.payments.confirm',$booking),['confirmation'=>1,'funds_verified'=>1,'payment_stage'=>'initial'])->assertConflict();
  $this->assertSame('pending',$treatment->fresh()->status);$this->assertSame('Pending Payment',$booking->fresh()->status);
  $this->confirm($treatment)->assertRedirect();
  $this->assertSame('approved',$treatment->fresh()->status);$this->assertSame('Confirmed',$booking->fresh()->status);
  $this->assertSame($original,$initial->fresh()->getRawOriginal());
  $this->assertTrue($confirmedAt->equalTo($booking->treatmentRequest->fresh()->payment_confirmed_at));
  $this->confirm($treatment)->assertConflict();$this->assertDatabaseCount('payment_reviews',2);
  $this->actingAs($owner)->get('/my-requests')->assertOk()->assertViewHas('requests',fn($rows)=>$rows->first()['status']==='confirmed');
 }
 public function test_filters_counts_and_details_use_transactions_and_do_not_mutate_history():void {
  [$booking,$initial,$admin,$owner]=$this->fixture();$this->actingAs($admin);$this->confirm($initial)->assertRedirect();
  $treatment=$this->startStepNine($booking,$owner);$this->actingAs($admin);
  $all=$this->get('/admin/payments')->assertOk()->assertViewHas('paymentTransactions',fn($rows)=>$rows->total()===2)
   ->assertViewHas('paymentStageCounts',fn($counts)=>$counts===['all'=>2,'initial'=>1,'treatment'=>1]);
  $initialPage=$this->get('/admin/payments?stage=initial')->assertOk()->assertDontSee('Deposit Summary')
   ->assertViewHas('paymentTransactions',fn($rows)=>$rows->total()===1&&$rows->first()->id===$initial->id);
  $this->get('/admin/payments?stage=treatment')->assertOk()->assertSee('Deposit Summary')->assertSee('Initial Deposit &mdash; Approved',false)->assertSee('THB 60,000.00')->assertSee('10%')
   ->assertDontSee('name="treatment_deposit_percentage"',false)->assertDontSee('Initial Deposit Amount')
   ->assertViewHas('paymentTransactions',fn($rows)=>$rows->total()===1&&$rows->first()->id===$treatment->id);
  $this->get('/admin/payments?status=Paid')->assertViewHas('paymentTransactions',fn($rows)=>$rows->total()===1&&$rows->first()->id===$initial->id);
  $this->get('/admin/payments?search='.$treatment->reference)->assertViewHas('paymentTransactions',fn($rows)=>$rows->total()===1&&$rows->first()->id===$treatment->id);
  $this->get('/admin/payments?from='.today()->format('Y-m-d'))->assertViewHas('paymentTransactions',fn($rows)=>$rows->total()===1&&$rows->first()->id===$initial->id);
  $this->assertDatabaseCount('payment_submissions',2);$this->assertDatabaseCount('payment_reviews',1);
 }
 public function test_step_nine_cannot_be_approved_without_step_four_or_with_a_changed_amount():void {
  [$booking,$initial,$admin,$owner]=$this->fixture();$this->actingAs($admin);$this->confirm($initial)->assertRedirect();
  $treatment=$this->startStepNine($booking,$owner);$this->actingAs($admin);
  $initial->update(['status'=>'rejected']);$this->confirm($treatment)->assertUnprocessable();
  $initial->update(['status'=>'approved']);$treatment->update(['amount_satang'=>6500000]);
  $booking->update(['deposit_amount_satang'=>6500000]);$this->confirm($treatment)->assertUnprocessable();
  $this->assertSame('pending',$treatment->fresh()->status);$this->assertSame('Pending Payment',$booking->fresh()->status);
  $this->assertDatabaseCount('payment_reviews',1);
 }
 public function test_step_nine_approval_requires_a_verified_customer_account_on_both_approval_routes():void {
  [$booking,$initial,$admin,$owner]=$this->fixture();
  $this->actingAs($admin);$this->confirm($initial)->assertRedirect();
  $treatment=$this->startStepNine($booking,$owner);
  $owner->forceFill(['email_verified_at'=>null])->save();
  $this->actingAs($admin);$this->confirm($treatment)->assertUnprocessable();
  $booking->update(['deposit_status'=>'Pending Verification']);
  $this->postJson(route('admin.payments.review',$treatment),[
   'decision'=>'approved','confirmation'=>1,'funds_verified'=>1,
   'notes'=>'Independent bank verification','verification_reference'=>'OTP-REQUIRED-TEST',
  ])->assertUnprocessable();
  $this->assertSame('pending',$treatment->fresh()->status);
  $this->assertSame('Pending Payment',$booking->fresh()->status);
  $this->assertDatabaseCount('payment_reviews',1);
 }
 public function test_transaction_confirmation_requires_admin_and_explicit_bank_verification():void {
  [$booking,$payment,$admin,$owner]=$this->fixture();
  $this->actingAs($owner);$this->confirm($payment)->assertForbidden();
  $this->actingAs($admin)->postJson(route('admin.payments.confirm-transaction',$payment),['confirmation'=>1])->assertUnprocessable();
  $this->assertSame('pending',$payment->fresh()->status);$this->assertDatabaseCount('payment_reviews',0);
 }
 public function test_backfill_keeps_existing_rows_and_does_not_create_duplicate_transactions():void {
  [$booking,$initial]=$this->fixture();$original=$initial->fresh()->getRawOriginal();
  [$legacy,$missing]=$this->fixture();$missing->delete();
  $migration=require database_path('migrations/2026_11_12_000002_backfill_payment_stage_records.php');
  $migration->up();$migration->up();
  $this->assertDatabaseCount('payment_submissions',2);$this->assertSame($original,$initial->fresh()->getRawOriginal());
  $this->assertDatabaseHas('payment_submissions',['booking_id'=>$legacy->id,'purpose'=>'initial','amount_satang'=>500000,'status'=>'pending']);
 }
 public function test_manual_booking_confirmation_requires_an_approved_step_nine_transaction():void {
  [$booking,$initial,$admin]=$this->fixture();$this->actingAs($admin);$this->confirm($initial)->assertRedirect();
  $booking->update(['deposit_phase'=>'treatment']);
  $this->postJson(route('admin.bookings.status',$booking),['status'=>'Confirmed','appointment_date'=>today()->addMonth()->format('Y-m-d')])->assertUnprocessable();
  $this->assertSame('Awaiting Hospital Confirmation',$booking->fresh()->status);
 }
 public function test_both_approval_routes_send_step_nine_email_to_customer_only_once():void {
  foreach(['confirm','review'] as $route){
   [$booking,$initial,$admin,$owner]=$this->fixture();$this->actingAs($admin);$this->confirm($initial)->assertRedirect();
   Notification::assertNothingSent();
   $payment=$this->startStepNine($booking,$owner);
   $this->get('/my-requests')->assertOk();Notification::assertNothingSent();
   if($route==='review')$this->post(route('guest.requests.payment-claim',$booking->treatmentRequest->reference),[])->assertRedirect();
   $this->actingAs($admin);
   $url=route($route==='review'?'admin.payments.review':'admin.payments.confirm-transaction',$payment);
   $data=['confirmation'=>1,'funds_verified'=>1,'decision'=>'approved','notes'=>'Checked bank records','verification_reference'=>'STEP9-MAIL-'.$payment->id];
   $this->post($url,$data)->assertRedirect()->assertSessionHas('status',fn($message)=>str_contains($message,'Confirmation email sent'));
   $this->assertSame('Confirmed',$booking->fresh()->status);
   Notification::assertSentOnDemandTimes(BookingDepositApproved::class,1);
   Notification::assertSentOnDemand(BookingDepositApproved::class,function($notification,$channels,$notifiable)use($booking,$payment,$owner){
    $this->assertSame($owner->email,$notifiable->routes['mail']);$this->assertSame(['mail'],$channels);
    $this->assertSame(6000000,$notification->amountSatang);
    $this->assertSame(65000000,$notification->totalTreatmentCostSatang);
    $this->assertSame(500000,$notification->initialDepositPaidSatang);
    $this->assertSame(6000000,$notification->bookingDepositPaidSatang);
    $this->assertSame(58500000,$notification->remainingBalanceSatang);
    $mail=$notification->toMail($notifiable);
    $this->assertSame('Booking Confirmed | AESTHETIC',$mail->subject);
    $this->assertSame(route('requests.index').'#my-bookings',$mail->actionUrl);
    $body=implode(' ',$mail->introLines);
    $this->assertStringContainsString('THB 60,000.00',$body);
    $this->assertStringContainsString('booking deposit payment of **THB 60,000.00**',$body);
    $this->assertStringContainsString('used to confirm your booking',$body);
    $this->assertStringContainsString('Total Treatment Cost: **THB 650,000.00**',$body);
    $this->assertStringContainsString('Initial Deposit Paid: **THB 5,000.00**',$body);
    $this->assertStringContainsString('Booking Deposit Paid: **THB 60,000.00**',$body);
    $this->assertStringContainsString('Remaining Balance: **THB 585,000.00**',$body);
    $this->assertStringContainsString('The remaining balance of THB 585,000.00 can be paid directly to your selected hospital or clinic on your treatment date.',$body);
    $this->assertStringNotContainsString('Step 4',$body);
    $this->assertStringNotContainsString('Step 9',$body);
    $this->assertStringNotContainsString('You do not need to make this payment again.',$body);
    $this->assertStringContainsString($booking->reference,$body);
    $this->assertStringContainsString($payment->reference,$body);
    $this->assertStringNotContainsString('Eye Surgery',$body);
    $this->assertStringContainsString('Confirmed',$mail->render()->toHtml());
    return true;
   });
   $data['verification_reference'].='-REPLAY';$this->postJson($url,$data)->assertConflict();
   $this->assertSame('already_sent',app(BookingDepositEmailNotification::class)->send($booking));
   Notification::assertSentOnDemandTimes(BookingDepositApproved::class,1);
   Notification::assertSentOnDemandTimes(GuestJourneyCode::class,0);
  }
 }
 public function test_mail_failure_does_not_reverse_payment_approval_or_expose_transport_details():void {
  [$booking,$initial,$admin,$owner]=$this->fixture();$this->actingAs($admin);$this->confirm($initial)->assertRedirect();
  $payment=$this->startStepNine($booking,$owner);$this->actingAs($admin);
  Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('SECRET-SMTP-CREDENTIALS'));
  $this->confirm($payment)->assertRedirect()->assertSessionHas('status',fn($message)=>str_contains($message,'Booking confirmed, but the confirmation email could not be sent')&&!str_contains($message,'SECRET'));
  $this->assertSame('approved',$payment->fresh()->status);$this->assertSame('Paid',$booking->fresh()->deposit_status);
  $this->assertSame('Confirmed',$booking->fresh()->status);
  Notification::fake();
  $this->assertSame('sent',app(BookingDepositEmailNotification::class)->send($booking));
  Notification::assertSentOnDemandTimes(BookingDepositApproved::class,1);
 }
 public function test_confirmation_email_uses_actual_cost_and_approved_payments_only():void {
  [$booking,$initial,$admin,$owner]=$this->fixture();
  $booking->quotation->update(['amount'=>'800000.00']);$initial->update(['amount_satang'=>300000]);$booking->update(['deposit_amount_satang'=>300000]);
  $this->actingAs($admin);$this->confirm($initial)->assertRedirect();
  $this->actingAs($owner)->postJson(route('bookings.treatment-deposit',$booking),['confirm_treatment'=>1])->assertOk()->assertJsonPath('deposit_satang',7700000);
  $payment=$booking->paymentSubmissions()->where('purpose','treatment')->firstOrFail();
  foreach(['pending','rejected','failed'] as $status){
   foreach(['initial','treatment'] as $purpose)$booking->paymentSubmissions()->create(['purpose'=>$purpose,'status'=>$status,'amount_satang'=>500000,'method'=>'Bank Transfer','submitted_at'=>now()]);
  }
  $this->actingAs($admin);$this->confirm($payment)->assertRedirect();
  Notification::assertSentOnDemand(BookingDepositApproved::class,function($notification){
   $this->assertSame(80000000,$notification->totalTreatmentCostSatang);
   $this->assertSame(300000,$notification->initialDepositPaidSatang);
   $this->assertSame(7700000,$notification->bookingDepositPaidSatang);
   $this->assertSame(72000000,$notification->remainingBalanceSatang);
   $body=implode(' ',$notification->toMail(null)->introLines);
   $this->assertStringContainsString('booking deposit payment of **THB 77,000.00**',$body);
   $this->assertStringContainsString('The remaining balance of THB 720,000.00 can be paid directly to your selected hospital or clinic on your treatment date.',$body);
   return true;
  });
 }
}
