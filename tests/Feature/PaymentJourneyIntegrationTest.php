<?php
namespace Tests\Feature;

use App\Models\{User,TreatmentRequest,Quotation,Booking,PaymentSubmission};
use App\Services\GuestBookingResume;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth,Mail,Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

/** Exercises real application routes and rendered email with isolated storage.
 * The external bank verification is a TEST operator assertion, not a bank API call.
 */
class PaymentJourneyIntegrationTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void {
  parent::setUp();Storage::fake('request_photos');
  config(['mail.default'=>'array']);
  $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
 }
 private function receipt():UploadedFile {
  return UploadedFile::fake()->createWithContent('TEST-ONLY-receipt.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3ioAAAAASUVORK5CYII='));
 }
 private function start():array {
  $response=$this->postJson(route('guest.requests.store'),[
   'submission_reference'=>(string)Str::uuid(),'name'=>'Payment Integration Test','email'=>'payment-test@example.test',
   'date_of_birth'=>'1995-03-15','gender'=>'Female','procedures'=>['Rhinoplasty'],
   'preferred_surgery_month'=>12,'preferred_surgery_year'=>now()->year+1,'goals'=>'Fictional test goals','consent_to_share'=>1,
  ])->assertCreated()->assertJsonPath('mail_sent',true);
  $cookie=$response->getCookie(GuestBookingResume::COOKIE,false)->getValue();
  $item=TreatmentRequest::firstOrFail();$hospital=User::factory()->create(['role'=>'hospital']);
  $this->actingAs($hospital)->get('/hospital/dashboard')->assertOk()->assertSee('Payment Integration Test');
  $this->post(route('hospital.quote',$item),['amount'=>'40000.00','details'=>'TEST ONLY quotation from hospital route','valid_until'=>today()->addDays(10)->format('Y-m-d')])->assertRedirect();
  $quote=Quotation::firstOrFail();$this->assertNotNull($quote->customer_emailed_at);
  Auth::logout();$this->post(route('guest.requests.accept',[$item->reference,$quote]),['expected_total_satang'=>4000000])->assertRedirect();
  $booking=Booking::firstOrFail();$this->assertSame(400000,$booking->deposit_amount_satang);$this->assertNull($booking->payment_verified_at);
  $this->get(route('guest.requests.show',$item->reference))->assertOk()->assertSee('I Have Paid')->assertSee('THB 4,000.00');
  return [$item,$booking,$cookie];
 }
 public function test_manual_payment_journey_with_actual_rendered_mail_cookie_return_and_account():void {
  [$item,$booking,$cookie]=$this->start();
  $payload=['method'=>'Bank Transfer','transaction_reference'=>'TEST-CUSTOMER-TRANSFER-1','proof'=>$this->receipt()];
  $this->post(route('guest.requests.payment-claim',$item->reference),$payload)->assertRedirect();
  $this->post(route('guest.requests.payment-claim',$item->reference),$payload)->assertRedirect();
  $submission=PaymentSubmission::firstOrFail();$this->assertDatabaseCount('bookings',1);$this->assertDatabaseCount('payment_submissions',1);
  Storage::disk('request_photos')->assertExists($submission->proof_path);
  $this->assertSame('Pending Verification',$booking->fresh()->deposit_status);$this->assertNull($booking->fresh()->payment_verified_at);
  $mailCount=Mail::mailer()->getSymfonyTransport()->messages()->count();
  $this->flushSession();$this->travel(2)->days();
  $this->withUnencryptedCookie(GuestBookingResume::COOKIE,$cookie)->get(route('guest.booking.resume'))->assertRedirect(route('guest.requests.show',$item->reference).'#booking-deposit');
  for($i=0;$i<2;$i++)$this->get(route('guest.requests.show',$item->reference))->assertOk()->assertSee('Payment Under Review')->assertDontSee('name="proof"',false);
  $this->assertSame($mailCount,Mail::mailer()->getSymfonyTransport()->messages()->count());
  $admin=User::factory()->create(['role'=>'admin']);$this->actingAs($admin)->get('/admin/payments?status=Pending%20Verification')->assertOk()->assertSee('Payment Details')->assertViewHas('paymentTransactions',fn($rows)=>$rows->first()->transaction_reference==='TEST-CUSTOMER-TRANSFER-1');
  $this->get(route('admin.payments.proof',$submission))->assertOk();
  $data=['decision'=>'approved','confirmation'=>1,'notes'=>'TEST operator verification fixture; no real transfer occurred','funds_verified'=>1,'verification_reference'=>'TEST-BANK-RESULT-1'];
  $this->post(route('admin.payments.review',$submission),$data)->assertRedirect();
  $this->assertSame('Paid',$booking->fresh()->deposit_status);$this->assertNotNull($booking->fresh()->payment_verified_at);$this->assertSame('Awaiting Hospital Confirmation',$booking->fresh()->status);
  $this->assertSame($admin->id,$submission->fresh()->reviewer_id);$this->assertNotNull($submission->fresh()->reviewed_at);
  $mail=Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
  $this->assertSame('payment-test@example.test',$mail->getTo()[0]->getAddress());
  $this->assertStringContainsString('Verify Your Email',$mail->getSubject());
  $this->assertSame(1,preg_match('/Verification Code:[\s*]+(\d{6})/',$mail->getTextBody(),$matches));
  $count=Mail::mailer()->getSymfonyTransport()->messages()->count();$data['verification_reference']='TEST-REPEATED-APPROVAL';
  $this->postJson(route('admin.payments.review',$submission),$data)->assertConflict();$this->assertSame($count,Mail::mailer()->getSymfonyTransport()->messages()->count());
  Auth::logout();$this->get(route('guest.requests.show',$item->reference))->assertSee('Payment Approved');
  $this->post(route('guest.requests.verify',$item->reference),['code'=>$matches[1]])->assertRedirect();
  $this->post(route('guest.requests.activate',$item->reference),['password'=>'OnlyForTesting123!','password_confirmation'=>'OnlyForTesting123!','terms'=>1])->assertRedirect(route('login'));
  $owner=User::where('email','payment-test@example.test')->firstOrFail();$this->assertGuest();$this->post(route('login.store'),['email'=>$owner->email,'password'=>'OnlyForTesting123!'])->assertRedirect();$this->assertAuthenticatedAs($owner);
  $this->get('/my-requests')->assertOk()->assertSee('My Bookings')->assertSee($booking->reference)->assertSee('THB 4,000.00');
  $this->assertSame($owner->id,$booking->fresh()->user_id);$this->assertDatabaseCount('bookings',1);
  $this->travelBack();
 }
}
