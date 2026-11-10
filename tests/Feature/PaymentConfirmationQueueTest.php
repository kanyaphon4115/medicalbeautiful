<?php
namespace Tests\Feature;
use App\Jobs\SendGuestActivationCode;
use App\Models\{Booking,Quotation,TreatmentRequest,User};
use App\Notifications\GuestJourneyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache,DB,Hash,Notification,Queue};
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentConfirmationQueueTest extends TestCase {
 use RefreshDatabase;
 private function fixture():array {
  $hospital=User::factory()->create(['role'=>'hospital']);
  $r=TreatmentRequest::create(['reference'=>(string)Str::uuid(),'name'=>'Test Customer','email'=>'queue-test@example.test','procedure'=>'Eye Surgery','goals'=>'Private medical goals must not be emailed','consented_at'=>now(),'consent_text'=>'TEST consent','status'=>'awaiting_hospital_confirmation','selected_hospital_id'=>$hospital->id,'selected_hospital_name'=>$hospital->name,'hospital_approved_at'=>now(),'payment_confirmed_at'=>now()]);
  $q=Quotation::create(['treatment_request_id'=>$r->id,'hospital_id'=>$hospital->id,'amount'=>40000,'details'=>'Private quotation details','valid_until'=>today()->addDays(7)]);
  $b=Booking::create(['treatment_request_id'=>$r->id,'quotation_id'=>$q->id,'hospital_id'=>$hospital->id,'status'=>'Awaiting Hospital Confirmation','deposit_status'=>'Paid','deposit_amount_satang'=>400000,'payment_verified_at'=>now(),'is_test'=>false,'payment_is_simulated'=>false]);
  $generation=(string)Str::uuid();$code='483921';$key='real-guest-code:'.$r->reference;
  Cache::put($key,['hash'=>Hash::make($code),'generation'=>$generation,'booking'=>$b->id,'email'=>$r->email,'expires'=>now()->addMinutes(10)->timestamp],now()->addMinutes(10));
  return [$r,$b,$key,new SendGuestActivationCode($r->id,$code,$generation)];
 }
 public function test_delivery_is_idempotent_and_contains_confirmation_signed_link_and_ten_minute_code():void {
  Notification::fake();[$r,$b,$key,$job]=$this->fixture();$job->handle();$job->handle();
  Notification::assertSentOnDemandTimes(GuestJourneyCode::class,1);
  Notification::assertSentOnDemand(GuestJourneyCode::class,function($n)use($r){
   $mail=$n->toMail(null);$this->assertSame('Payment Confirmed – Verify Your Email | AESTHETIC',$mail->subject);
   $this->assertSame('Verify Your Email',$mail->actionText);$this->assertStringContainsString('signature=',$mail->actionUrl);
   $this->assertStringContainsString($r->reference,$mail->actionUrl);$text=implode(' ',$mail->introLines);
   $this->assertStringContainsString('10 minutes',$text);$this->assertStringNotContainsString($r->goals,$text);return true;
  });
  $record=Cache::get($key);$this->assertTrue(Hash::check('483921',$record['hash']));$this->assertArrayNotHasKey('code',$record);$this->assertNotEmpty($record['delivered_at']);
 }
 public function test_failed_delivery_can_retry_same_code_and_redacts_transport_errors():void {
  [$r,$b,$key,$job]=$this->fixture();Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('SECRET-SMTP-DETAIL'));
  try{$job->handle();$this->fail('Expected delivery error');}catch(\RuntimeException $e){$this->assertStringNotContainsString('SECRET-SMTP-DETAIL',$e->getMessage());}
  $this->assertArrayNotHasKey('delivered_at',Cache::get($key));Notification::fake();$job->handle();Notification::assertSentOnDemandTimes(GuestJourneyCode::class,1);
 }
 public function test_stale_expired_consumed_and_reversed_jobs_do_not_send_codes():void {
  Notification::fake();[$r,$b,$key,$job]=$this->fixture();$record=Cache::get($key);$record['generation']='new-generation';Cache::put($key,$record,600);$job->handle();
  Cache::forget($key);$job->handle();
  [$r,$b,$key,$job]=$this->fixture();$b->update(['deposit_status'=>'Refunded']);$job->handle();
  [$r,$b,$key,$job]=$this->fixture();$this->travel(11)->minutes();$job->handle();$this->travelBack();
  Notification::assertSentOnDemandTimes(GuestJourneyCode::class,0);
 }
 public function test_database_queue_encrypts_otp_payload():void {
  [$r,$b,$key,$job]=$this->fixture();$id=Queue::connection('database')->push($job,'','payment-mail');
  $payload=DB::table('jobs')->where('id',$id)->value('payload');$this->assertNotNull($payload);
  $this->assertStringNotContainsString('483921',$payload);$this->assertStringNotContainsString($r->email,$payload);
  $this->assertSame(3,$job->tries);$this->assertSame([30,60,120],$job->backoff());
 }
}
