<?php
namespace Tests\Feature;
use App\Models\{User,TreatmentRequest,RequestPhoto,Quotation,Booking};
use App\Notifications\{GuestRequestAccess,GuestJourneyCode};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Notification,Storage,DB,URL,Auth};
use Illuminate\Support\Str;
use Tests\TestCase;
class RealGuestRequestTest extends TestCase{
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();Notification::fake();Storage::fake('request_photos');$this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);}
 private function data():array{return ['submission_reference'=>(string)Str::uuid(),'name'=>'Real Guest Example','email'=>'guest@example.test','line_id'=>'guest-line','phone_number'=>'+66 81 234 5678','date_of_birth'=>'1995-03-15','gender'=>'Female','procedures'=>['Eye Surgery','Rhinoplasty'],'preferred_surgery_month'=>12,'preferred_surgery_year'=>now()->year+1,'goals'=>'Private beauty goals','additional_notes'=>'Private additional notes','consent_to_share'=>1];}
 private function photo(){return UploadedFile::fake()->createWithContent('private.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3ioAAAAASUVORK5CYII='));}
 private function submitted():TreatmentRequest{$data=$this->data();$data['photos']=[$this->photo()];$this->postJson(route('guest.requests.store'),$data)->assertCreated();return TreatmentRequest::latest('id')->firstOrFail();}
 private function quote(TreatmentRequest $item):Quotation{$h=User::factory()->create(['role'=>'hospital']);return Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$h->id,'amount'=>'60000.00','details'=>'Actual quotation details entered by the hospital','valid_until'=>now()->addDays(10)->format('Y-m-d')]);}
 private function accepted():TreatmentRequest{$item=$this->submitted();$q=$this->quote($item);$this->post(route('guest.requests.accept',[$item->reference,$q]),['expected_total_satang'=>6000000])->assertRedirect();return $item->fresh();}
 private function realPaid(TreatmentRequest $item):void{$item->booking->update(['deposit_status'=>'Paid','status'=>'Confirmed','payment_verified_at'=>now(),'payment_is_simulated'=>false]);$item->update(['payment_confirmed_at'=>now(),'status'=>'confirmed']);}
 private function verify(TreatmentRequest $item):void{$this->post(route('guest.requests.code',$item->reference))->assertRedirect();$code=null;Notification::assertSentOnDemand(GuestJourneyCode::class,function($n)use(&$code){$code=$n->code;$this->assertTrue($n->real);return true;});$this->post(route('guest.requests.verify',$item->reference),['code'=>$code])->assertRedirect();}
 public function test_guest_submission_persists_real_request_and_private_photos_without_creating_user_or_booking():void{
  $data=$this->data();$data['photos']=[$this->photo()];$result=$this->postJson(route('guest.requests.store'),$data)->assertCreated()->assertJsonPath('mail_sent',true);$item=TreatmentRequest::firstOrFail();
  $this->assertNull($item->user_id);$this->assertSame('pending',$item->status);$this->assertSame(['Eye Surgery','Rhinoplasty'],$item->procedure_names);$this->assertSame('1995-03-15',$item->date_of_birth);$this->assertSame('Private additional notes',$item->additional_notes);$this->assertSame('+66812345678',$item->phone_number);$this->assertDatabaseCount('users',0);$this->assertDatabaseCount('bookings',0);$this->assertDatabaseCount('quotations',0);
  $this->assertNotSame('1995-03-15',DB::table('treatment_requests')->value('date_of_birth'));$photo=$item->photos()->firstOrFail();Storage::disk('request_photos')->assertExists($photo->path);
  Notification::assertSentOnDemand(GuestRequestAccess::class,fn($n)=>$n->real&&str_contains($n->url,$item->reference));
  $this->get($result->json('redirect'))->assertOk()->assertSee('No hospital has responded yet')->assertSee('Private additional notes')->assertDontSee('Simulate verified payment')->assertDontSee('Frontend preview');
  $this->postJson(route('guest.requests.store'),$data)->assertCreated();$this->assertDatabaseCount('treatment_requests',1);$this->assertDatabaseCount('request_photos',1);
 }
 public function test_saved_request_remains_available_if_email_transport_fails():void{
  Notification::swap(new class{public function route(...$arguments){throw new \RuntimeException('secret transport detail');}});
  $this->postJson(route('guest.requests.store'),$this->data())->assertCreated()->assertJsonPath('mail_sent',false)->assertDontSee('secret transport detail');$this->assertDatabaseCount('treatment_requests',1);$this->assertDatabaseCount('users',0);
 }
 public function test_private_link_and_photo_access_require_the_right_session_or_signed_email_link():void{
  $item=$this->submitted();$photo=$item->photos->first();$this->get(route('guest.requests.photo',[$item->reference,$photo]))->assertOk()->assertHeader('Cache-Control','no-store, private');$this->flushSession();
  $this->get(route('guest.requests.show',$item->reference))->assertForbidden();$this->get(route('guest.requests.photo',[$item->reference,$photo]))->assertForbidden();$this->get(route('guest.requests.access',$item->reference))->assertForbidden();
  $url=URL::temporarySignedRoute('guest.requests.access',now()->addDays(7),['reference'=>$item->reference]);$this->get($url)->assertRedirect(route('guest.requests.show',$item->reference));$this->get(route('guest.requests.show',$item->reference))->assertOk();
  $other=$this->submitted();$this->get(route('guest.requests.photo',[$other->reference,$photo]))->assertNotFound();$this->get('/request-photos/'.$photo->id)->assertRedirect(route('login'));
 }
 public function test_hospital_and_admin_see_the_same_real_guest_request_and_privacy_fields():void{
  $item=$this->submitted();$hospital=User::factory()->create(['role'=>'hospital']);$admin=User::factory()->create(['role'=>'admin']);
  $this->actingAs($hospital)->get('/hospital/dashboard')->assertOk()->assertSee('Real Guest Example')->assertSee('Private additional notes')->assertSee('1995-03-15');$this->get('/request-photos/'.$item->photos->first()->id)->assertOk();
  $this->actingAs($admin)->get('/admin/requests')->assertOk()->assertSee('Real Guest Example')->assertSee('Private additional notes');
  $this->actingAs(User::factory()->create())->get('/request-photos/'.$item->photos->first()->id)->assertNotFound();
 }
 public function test_real_quotation_acceptance_creates_exactly_one_unpaid_non_test_booking_and_uses_ten_percent():void{
  $item=$this->submitted();$q=$this->quote($item);$url=route('guest.requests.accept',[$item->reference,$q]);
  $this->post($url,['expected_total_satang'=>6000000])->assertRedirect();$this->post($url,['expected_total_satang'=>6000000])->assertRedirect();$this->assertDatabaseCount('bookings',1);
  $b=Booking::firstOrFail();$this->assertSame('Pending Payment',$b->status);$this->assertSame('Pending',$b->deposit_status);$this->assertSame(500000,$b->deposit_amount_satang);$this->assertFalse($b->is_test);$this->assertFalse($b->payment_is_simulated);$this->assertNull($b->user_id);$this->assertTrue($b->is_payment_eligible);
  $this->get(route('guest.requests.show',$item->reference))->assertOk()->assertSee('After your deposit payment is confirmed, a 6-digit OTP will be sent to your email address.')->assertDontSee('Create Your Account');
  $different=$this->quote($this->submitted());$this->post(route('guest.requests.accept',[$item->reference,$different]),['expected_total_satang'=>6000000])->assertNotFound();
 }
 public function test_payment_and_code_verification_cannot_be_faked_from_the_browser():void{
  $item=$this->accepted();$this->postJson(route('guest.requests.code',$item->reference),['paid'=>true])->assertUnprocessable();$this->postJson(route('guest.requests.activate',$item->reference),['verified'=>true])->assertUnprocessable();
  $item->booking->update(['deposit_status'=>'Paid','payment_is_simulated'=>true,'payment_verified_at'=>now()]);$item->update(['payment_confirmed_at'=>now()]);$this->postJson(route('guest.requests.code',$item->reference))->assertUnprocessable();$this->assertDatabaseCount('users',1);
 }
 public function test_verified_real_payment_and_emailed_code_allow_real_account_creation_and_linking():void{
  $item=$this->accepted();$this->realPaid($item);$this->postJson(route('guest.requests.activate',$item->reference),[])->assertForbidden();$this->verify($item);
  $this->post(route('guest.requests.activate',$item->reference),['password'=>'StrongPassword123!','password_confirmation'=>'StrongPassword123!','terms'=>1])->assertRedirect(route('login'));
  $owner=User::where('email',$item->email)->firstOrFail();$this->assertGuest();$this->post(route('login.store'),['email'=>$owner->email,'password'=>'StrongPassword123!'])->assertRedirect();$this->assertAuthenticatedAs($owner);$this->assertSame('user',$owner->role);$this->assertNotNull($owner->email_verified_at);$this->assertSame($owner->id,$item->fresh()->user_id);$this->assertSame($owner->id,$item->fresh()->booking->user_id);$this->get('/my-requests')->assertSee('Eye Surgery, Rhinoplasty');
 }
 public function test_existing_account_requires_real_login_before_linking_the_verified_guest_booking():void{
  $owner=User::factory()->create(['email'=>'guest@example.test','password'=>'ExistingPassword123!']);$item=$this->accepted();$this->realPaid($item);$this->verify($item);
  $this->post(route('guest.requests.activate',$item->reference))->assertRedirect(route('login'));$this->assertNull($item->fresh()->user_id);
  $this->post('/login',['email'=>$owner->email,'password'=>'ExistingPassword123!'])->assertRedirect(route('requests.index'));$this->assertSame($owner->id,$item->fresh()->user_id);$this->assertDatabaseCount('users',2);
 }
 public function test_invalid_dates_procedures_consent_and_photo_types_are_rejected():void{
  $data=$this->data();$data['date_of_birth']=now()->addYear()->format('Y-m-d');$data['procedures']=['Fake Surgery'];$data['consent_to_share']=0;$data['photos']=[UploadedFile::fake()->create('unsafe.svg',1,'image/svg+xml')];
  $this->postJson(route('guest.requests.store'),$data)->assertUnprocessable()->assertJsonValidationErrors(['date_of_birth','procedures.0','consent_to_share','photos.0']);$this->assertDatabaseCount('treatment_requests',0);
 }

 public function test_real_payment_report_survives_return_link_and_never_confirms_money():void{
  $item=$this->accepted();$booking=$item->booking;
  $this->post(route('guest.requests.payment-claim',$item->reference),['method'=>'Bank Transfer','transaction_reference'=>'test-transfer-123'])->assertRedirect();
  $this->assertSame('Pending Verification',$booking->fresh()->deposit_status);$this->assertNull($booking->fresh()->payment_verified_at);$this->assertSame('Pending Payment',$booking->fresh()->status);
  $this->get(route('guest.requests.show',$item->reference))->assertOk()->assertSee('Payment Under Review')->assertDontSee('Report an Existing Deposit for Review');
  $this->flushSession();$this->post(route('guest.requests.payment-claim',$item->reference),['method'=>'Bank Transfer','transaction_reference'=>'test-transfer-123'])->assertForbidden();
  $url=URL::temporarySignedRoute('guest.requests.access',now()->addDays(7),['reference'=>$item->reference]);$this->get($url)->assertRedirect();
  $this->get(route('guest.requests.show',$item->reference))->assertSee('Payment Under Review');$this->assertDatabaseCount('bookings',1);
 }
 public function test_public_form_uses_real_guest_endpoint_without_demo_resume():void{
  $this->get('/')->assertOk()->assertSee('data-real-request-store=',false)->assertDontSee('data-notification-demo=',false)->assertDontSee('data-demo-resume-store=',false);
 }

 public function test_persistent_return_capability_restores_review_after_session_loss_and_expires():void{
  $item=$this->accepted();$this->post(route('guest.requests.payment-claim',$item->reference),['method'=>'Bank Transfer','transaction_reference'=>'test-transfer-123'])->assertRedirect();
  $token=str_repeat('A',64);\Illuminate\Support\Facades\Cache::put('guest-return:'.hash('sha256',$token),$item->reference,now()->addDays(7));
  $this->flushSession();$this->withCookie(\App\Services\GuestBookingResume::COOKIE,$token)->get(route('guest.booking.resume'))->assertRedirect(route('guest.requests.show',$item->reference).'#booking-deposit');
  $this->get(route('guest.requests.show',$item->reference))->assertOk()->assertSee('Payment Under Review')->assertSee('Contact Support')->assertSee('data-payment-state="pending_verification"',false)->assertDontSee('Report an Existing Deposit for Review');
  $this->assertDatabaseCount('bookings',1);$this->assertNull($item->booking->fresh()->payment_verified_at);
  $this->travel(8)->days();$this->get(route('guest.booking.resume'))->assertForbidden();$this->travelBack();
 }
 public function test_booking_id_or_invalid_return_cookie_cannot_authorize_guest_resume():void{
  $item=$this->accepted();$this->flushSession();
  $this->withCookie(\App\Services\GuestBookingResume::COOKIE,'BK-2026-000001')->get(route('guest.booking.resume'))->assertForbidden();
  $this->get(route('guest.requests.show',$item->reference))->assertForbidden();
 }
 public function test_resume_tracks_verified_payment_then_email_then_existing_dashboard():void{
  $item=$this->accepted();$this->realPaid($item);
  $this->get(route('guest.booking.resume'))->assertRedirect(route('guest.requests.show',$item->reference).'#guest-verification');
  $this->get(route('guest.requests.show',$item->reference))->assertSee('id="guest-verification"',false)->assertDontSee('id="guest-account"',false);
  $this->verify($item);
  $this->get(route('guest.booking.resume'))->assertRedirect(route('guest.requests.show',$item->reference).'#guest-account');
  $this->get(route('guest.requests.show',$item->reference))->assertSee('id="guest-account"',false)->assertDontSee('name="code"',false);
  $this->post(route('guest.requests.activate',$item->reference),['password'=>'StrongPassword123!','password_confirmation'=>'StrongPassword123!','terms'=>1])->assertRedirect(route('login'));
  $this->assertGuest();$this->post(route('login.store'),['email'=>$item->email,'password'=>'StrongPassword123!'])->assertRedirect();$this->get(route('guest.booking.resume'))->assertRedirect(route('requests.index'));$this->assertDatabaseCount('bookings',1);
 }
 public function test_status_rules_never_treat_unverified_paid_flag_as_money_received():void{
  $item=$this->accepted();$booking=$item->booking;
  foreach(['Pending'=>'pending_payment','Pending Verification'=>'pending_verification','Failed'=>'failed','Expired'=>'expired','Refunded'=>'refunded','Paid'=>'pending_verification'] as $stored=>$state){
   $booking->update(['deposit_status'=>$stored]);
   $this->get(route('guest.requests.show',$item->reference))->assertOk()->assertViewHas('progress',fn($p)=>$p['status']===$state)->assertDontSee('name="code"',false);
  }
 }
 public function test_submission_issues_private_persistent_cookie_and_signed_return_link_sets_latest_request():void{
  $response=$this->postJson(route('guest.requests.store'),$this->data())->assertCreated()->assertCookie(\App\Services\GuestBookingResume::COOKIE);
  $this->assertTrue($response->getCookie(\App\Services\GuestBookingResume::COOKIE)->isHttpOnly());
  $item=TreatmentRequest::latest('id')->firstOrFail();$this->flushSession();
  $link=URL::temporarySignedRoute('guest.requests.access',now()->addDays(7),['reference'=>$item->reference]);
  $this->get($link)->assertRedirect()->assertSessionHas('real_guest_latest',$item->reference)->assertCookie(\App\Services\GuestBookingResume::COOKIE);
 }

 private function paymentSubmission(TreatmentRequest $item){
  $this->post(route('guest.requests.payment-claim',$item->reference),['method'=>'Bank Transfer','transaction_reference'=>'bank-claim-'.Str::uuid(),'proof'=>$this->photo()])->assertRedirect();
  return $item->booking->paymentSubmissions()->firstOrFail();
 }
 public function test_admin_approval_to_code_activation_dashboard_is_persistent_and_idempotent():void{
  $item=$this->accepted();$submission=$this->paymentSubmission($item);$admin=User::factory()->create(['role'=>'admin']);
  $this->post(route('guest.requests.payment-claim',$item->reference),['method'=>'Bank Transfer','transaction_reference'=>'duplicate'])->assertRedirect();$this->assertDatabaseCount('payment_submissions',1);
  $this->actingAs($admin)->get('/admin/payments?status=Pending%20Verification')->assertOk()->assertSee('Payment Details')->assertSee('Download Private Payment Proof');
  $data=['decision'=>'approved','confirmation'=>1,'notes'=>'Amount and recipient verified against bank statement','funds_verified'=>1,'verification_reference'=>'BANK-APPROVED-ONE'];
  $this->post(route('admin.payments.review',$submission),$data)->assertRedirect();
  $this->assertSame('Paid',$item->booking->fresh()->deposit_status);$this->assertSame('Awaiting Hospital Confirmation',$item->booking->fresh()->status);
  $this->assertSame($admin->id,$submission->fresh()->reviewer_id);$this->assertDatabaseCount('payment_reviews',1);
  $data['verification_reference']='BANK-OTHER';$this->postJson(route('admin.payments.review',$submission),$data)->assertConflict();
  Notification::assertSentOnDemandTimes(GuestJourneyCode::class,1);$code=null;Notification::assertSentOnDemand(GuestJourneyCode::class,function($n)use(&$code){$code=$n->code;return true;});
  Auth::logout();$this->post(route('guest.requests.verify',$item->reference),['code'=>$code])->assertRedirect();
  $this->get(route('guest.requests.show',$item->reference))->assertSee('Create Your Account')->assertDontSee('name="code"',false);
  $this->post(route('guest.requests.activate',$item->reference),['password'=>'StrongPassword123!','password_confirmation'=>'StrongPassword123!','terms'=>1])->assertRedirect(route('login'));
  $owner=User::where('email',$item->email)->firstOrFail();$this->assertGuest();$this->post(route('login.store'),['email'=>$owner->email,'password'=>'StrongPassword123!'])->assertRedirect();$this->assertAuthenticatedAs($owner);$this->assertSame($item->phone_number,$owner->phone_number);$this->assertSame($item->date_of_birth,$owner->date_of_birth);
  $this->assertSame($owner->id,$submission->booking->fresh()->user_id);$this->assertTrue($item->fresh()->canChatWithHospital());
  $this->get('/my-requests')->assertOk()->assertSee('Eye Surgery, Rhinoplasty');
  $this->assertDatabaseCount('bookings',1);$this->assertDatabaseCount('payment_submissions',1);
 }
 public function test_rejection_and_information_request_preserve_proof_history_without_otp():void{
  $item=$this->accepted();$first=$this->paymentSubmission($item);$admin=User::factory()->create(['role'=>'admin']);
  $this->actingAs($admin)->post(route('admin.payments.review',$first),['decision'=>'rejected','confirmation'=>1,'notes'=>'Recipient does not match'])->assertRedirect();
  Notification::assertSentOnDemandTimes(GuestJourneyCode::class,0);Auth::logout();
  $this->get(route('guest.requests.show',$item->reference))->assertOk()->assertSee('Payment Verification Unsuccessful')->assertSee('Recipient does not match')->assertSee('I Have Paid')->assertSee('name="proof"',false);
  $second=$this->paymentSubmission($item);$this->assertNotSame($first->id,$second->id);$this->assertSame('rejected',$first->fresh()->status);
  $this->actingAs($admin)->post(route('admin.payments.review',$second),['decision'=>'information_requested','confirmation'=>1,'notes'=>'Please send a clearer receipt'])->assertRedirect();
  $this->assertSame('Information Required',$item->booking->fresh()->deposit_status);$this->assertDatabaseCount('payment_submissions',2);$this->assertDatabaseCount('payment_reviews',2);$this->assertDatabaseCount('bookings',1);
 }
 public function test_payment_review_permissions_private_proofs_and_bank_evidence_are_enforced():void{
  $item=$this->accepted();$submission=$this->paymentSubmission($item);$url=route('admin.payments.review',$submission);
  foreach(['user','hospital'] as $role){$this->actingAs(User::factory()->create(['role'=>$role]))->postJson($url,['decision'=>'approved'])->assertForbidden();$this->get(route('admin.payments.proof',$submission))->assertForbidden();}
  $this->actingAs(User::factory()->create(['role'=>'admin']));
  $this->postJson($url,['decision'=>'approved','confirmation'=>1,'notes'=>'Customer claimed payment'])->assertUnprocessable();
  $this->get(route('admin.payments.proof',$submission))->assertOk()->assertHeader('Cache-Control','no-store, private');
  $this->assertSame('Pending Verification',$item->booking->fresh()->deposit_status);Notification::assertSentOnDemandTimes(GuestJourneyCode::class,0);
 }
 public function test_multiple_requests_offer_selection_without_redirecting_home_or_cross_access():void{
  $one=$this->submitted();$two=$this->submitted();
  $this->get('/')->assertOk()->assertDontSee('Your existing request is saved.')->assertDontSee('Continue Your Request / Payment');
  $this->get(route('guest.booking.resume'))->assertOk()->assertSee($one->procedure_label)->assertSee('Choose the request');
  $this->get(route('guest.booking.resume',['request'=>(string)Str::uuid()]))->assertForbidden();
  $this->get(route('guest.booking.resume',['request'=>$one->reference]))->assertRedirect(route('guest.requests.show',$one->reference).'#booking-deposit');
  $this->assertDatabaseCount('treatment_requests',2);
 }

 public function test_guest_and_admin_pages_remain_available_before_payment_tables_are_deployed():void{
  $item=$this->accepted();\Illuminate\Support\Facades\Schema::drop('payment_reviews');\Illuminate\Support\Facades\Schema::drop('payment_submissions');
  $this->get(route('guest.requests.show',$item->reference))->assertOk()->assertSee('Your request and booking are saved.')->assertSee($item->booking->reference);
  $this->actingAs(User::factory()->create(['role'=>'admin']))->get('/admin/payments')->assertOk();
 }

 public function test_admin_can_request_missing_evidence_and_customer_can_submit_it_on_existing_booking():void{
  $item=$this->accepted();$booking=$item->booking;$admin=User::factory()->create(['role'=>'admin']);
  $this->actingAs($admin)->get('/admin/payments?record='.$booking->id)->assertOk()->assertSee('Confirm Payment')->assertSee(route('admin.payments.confirm-transaction',$booking->paymentSubmissions()->first()),false)->assertDontSee('Request New Payment Slip');
  $this->post(route('admin.payments.request-evidence',$booking),['notes'=>'Please provide bank reference or receipt','confirmation'=>1])->assertRedirect();
  $this->assertSame('Information Required',$booking->fresh()->deposit_status);$this->assertDatabaseCount('payment_reviews',1);Notification::assertSentOnDemand(\App\Notifications\PaymentEvidenceRequested::class,fn($n)=>str_contains($n->url,$item->reference));
  Notification::assertSentOnDemandTimes(GuestJourneyCode::class,0);Auth::logout();
  $this->get(route('guest.requests.show',$item->reference))->assertOk()->assertSee('Please provide bank reference or receipt')->assertSee('I Have Paid');
  $this->paymentSubmission($item);$this->assertDatabaseCount('bookings',1);$this->assertDatabaseCount('payment_submissions',2);$this->assertSame('Pending Verification',$booking->fresh()->deposit_status);
 }
 public function test_requesting_evidence_requires_admin_authorization_and_notes():void{
  $item=$this->accepted();$booking=$item->booking;
  foreach(['user','hospital'] as $role)$this->actingAs(User::factory()->create(['role'=>$role]))->postJson(route('admin.payments.request-evidence',$booking),['notes'=>'Please provide proof','confirmation'=>1])->assertForbidden();
  $this->actingAs(User::factory()->create(['role'=>'admin']))->postJson(route('admin.payments.request-evidence',$booking),['confirmation'=>1])->assertUnprocessable();
  $this->assertDatabaseCount('payment_reviews',0);
 }
 public function test_payment_notification_without_upload_requires_independent_admin_verification():void{
  $item=$this->accepted();$booking=$item->booking;
  $this->post(route('guest.requests.payment-claim',$item->reference),[])->assertRedirect();
  $this->post(route('guest.requests.payment-claim',$item->reference),[])->assertRedirect();
  $this->assertDatabaseCount('payment_submissions',1);
  $row=$booking->paymentSubmissions()->firstOrFail();
  $this->actingAs(User::factory()->create(['role'=>'admin']))->postJson(route('admin.payments.review',$row),['decision'=>'approved','confirmation'=>1,'notes'=>'Checked bank','funds_verified'=>1,'verification_reference'=>'NO-EVIDENCE-REFERENCE'])->assertRedirect();
  $this->assertSame('Paid',$booking->fresh()->deposit_status);$this->assertDatabaseCount('payment_reviews',1);Notification::assertSentOnDemandTimes(GuestJourneyCode::class,1);
 }

 public function test_admin_direct_confirmation_without_customer_submission_is_authorized_and_idempotent():void{
  $item=$this->accepted();$booking=$item->booking;$url=route('admin.payments.confirm',$booking);
  $this->actingAs(User::factory()->create(['role'=>'hospital']))->postJson($url,['confirmation'=>1,'funds_verified'=>1])->assertForbidden();
  $admin=User::factory()->create(['role'=>'admin']);$this->actingAs($admin)->postJson($url,[])->assertUnprocessable();
  $this->assertDatabaseCount('payment_submissions',1);
  $this->post($url,['confirmation'=>1,'funds_verified'=>1])->assertRedirect();
  $this->assertSame('Paid',$booking->fresh()->deposit_status);$this->assertSame($admin->id,$booking->fresh()->payment_verified_by);
  $this->assertDatabaseCount('payment_submissions',1);$this->assertDatabaseCount('payment_reviews',1);
  Notification::assertSentOnDemandTimes(GuestJourneyCode::class,1);
  $this->postJson($url,['confirmation'=>1,'funds_verified'=>1])->assertConflict();
  $this->assertDatabaseCount('payment_reviews',1);Notification::assertSentOnDemandTimes(GuestJourneyCode::class,1);
 }
 public function test_new_customer_email_button_opens_otp_without_login_in_fresh_session():void{
  $item=$this->accepted();$this->realPaid($item);
  $this->post(route('guest.requests.code',$item->reference))->assertRedirect();
  $code=null;$link=null;
  Notification::assertSentOnDemand(GuestJourneyCode::class,function($n)use(&$code,&$link){$code=$n->code;$link=$n->url;return true;});
  $this->assertStringContainsString('/verify-email?',$link);
  $this->flushSession();
  $this->get(route('guest.requests.verification-entry',$item->reference))->assertForbidden();
  $this->get($link)->assertOk()->assertSee('data-guest-otp-dialog',false)->assertSee('Enter the 6-digit code sent to your email.');
  $this->postJson(route('guest.requests.verify',$item->reference),['code'=>$code])->assertOk()->assertJsonPath('redirect',route('guest.requests.account',$item->reference));
  $this->get(route('guest.requests.account',$item->reference))->assertOk()->assertSee('Create Your Account')->assertSee('name="password_confirmation"',false);
  $this->assertDatabaseMissing('users',['email'=>$item->email]);
 }
 public function test_verified_email_matching_admin_explains_existing_account_without_creating_duplicate():void{
  $item=$this->accepted();$this->realPaid($item);User::factory()->create(['email'=>$item->email,'role'=>'admin']);
  $this->verify($item);
  $this->get(route('guest.requests.account',$item->reference))->assertOk()->assertSee('Connect Your Existing Account')->assertSee('Super Admin')->assertSee($item->email)->assertDontSee('name="password_confirmation"',false);
  $this->assertNull($item->fresh()->user_id);
 }
 public function test_bank_verification_reference_cannot_approve_two_different_bookings():void{
  $first=$this->accepted();$one=$this->paymentSubmission($first);$second=$this->accepted();$two=$this->paymentSubmission($second);
  $this->actingAs(User::factory()->create(['role'=>'admin']));
  $data=['decision'=>'approved','confirmation'=>1,'notes'=>'TEST independent bank verification','funds_verified'=>1,'verification_reference'=>'TEST-UNIQUE-TRANSFER'];
  $this->post(route('admin.payments.review',$one),$data)->assertRedirect();
  $data['verification_reference']=' test-unique-transfer ';$this->postJson(route('admin.payments.review',$two),$data)->assertUnprocessable()->assertJsonValidationErrors('verification_reference');
  $this->assertSame('Pending Verification',$second->booking->fresh()->deposit_status);$this->assertDatabaseCount('payment_reviews',1);Notification::assertSentOnDemandTimes(GuestJourneyCode::class,1);
 }
 public function test_real_activation_code_expires_is_attempt_limited_and_cannot_be_reused():void{
  $item=$this->accepted();$this->realPaid($item);$this->post(route('guest.requests.code',$item->reference))->assertRedirect();
  $code=null;Notification::assertSentOnDemand(GuestJourneyCode::class,function($n)use(&$code){$code=$n->code;return true;});
  $this->travel(11)->minutes();$this->postJson(route('guest.requests.verify',$item->reference),['code'=>$code])->assertUnprocessable();
  $this->post(route('guest.requests.code',$item->reference))->assertRedirect();
  Notification::assertSentOnDemand(GuestJourneyCode::class,function($n)use(&$code){$code=$n->code;return true;});
  $wrong=$code==='000000'?'000001':'000000';
  for($i=0;$i<5;$i++)$this->postJson(route('guest.requests.verify',$item->reference),['code'=>$wrong])->assertUnprocessable();
  $this->postJson(route('guest.requests.verify',$item->reference),['code'=>$code])->assertStatus(429);
  $this->travel(11)->minutes();$this->post(route('guest.requests.code',$item->reference))->assertRedirect();
  Notification::assertSentOnDemand(GuestJourneyCode::class,function($n)use(&$code){$code=$n->code;return true;});
  $this->post(route('guest.requests.verify',$item->reference),['code'=>$code])->assertRedirect();
  $this->postJson(route('guest.requests.verify',$item->reference),['code'=>$code])->assertUnprocessable();
  $this->travelBack();
 }

 public function test_six_digit_modal_server_verification_redirects_new_guest_to_account_page():void{
  $this->freezeTime();
  $item=$this->accepted();$this->realPaid($item);$this->post(route('guest.requests.code',$item->reference))->assertRedirect();
  $html=$this->get(route('guest.requests.show',$item->reference))->assertOk()->assertSee('Verify Your Email')->getContent();$this->assertSame(6,substr_count($html,'data-otp-digit'));
  $record=\Illuminate\Support\Facades\Cache::get('real-guest-code:'.$item->reference);$this->assertSame(600,$record['expires']-now()->timestamp);
  $code=null;Notification::assertSentOnDemand(GuestJourneyCode::class,function($n)use(&$code){$code=$n->code;return true;});
  $this->post(route('guest.requests.verify',$item->reference),['code'=>'invalid'])->assertRedirect()->assertSessionHasErrors('code')->assertSessionMissing('_old_input.code');
  $this->postJson(route('guest.requests.verify',$item->reference),['digits'=>['1','2']])->assertUnprocessable();
  $this->postJson(route('guest.requests.verify',$item->reference),['digits'=>str_split($code)])->assertOk()->assertJsonPath('message','Email Verified Successfully')->assertJsonPath('redirect',route('guest.requests.account',$item->reference));
  $this->get(route('guest.requests.account',$item->reference))->assertOk()->assertSee('Create Your Account')->assertSee('name="username"',false)->assertSee($item->email)->assertDontSee('name="digits[]"',false);
 }
 public function test_json_verification_for_existing_email_requires_secure_login_before_linking():void{
  $owner=User::factory()->create(['email'=>'guest@example.test']);$item=$this->accepted();$this->realPaid($item);
  $this->post(route('guest.requests.code',$item->reference))->assertRedirect();$code=null;Notification::assertSentOnDemand(GuestJourneyCode::class,function($n)use(&$code){$code=$n->code;return true;});
  $this->postJson(route('guest.requests.verify',$item->reference),['code'=>$code])->assertOk()->assertJsonPath('redirect',route('guest.requests.account',$item->reference))->assertSessionHas('real_guest_link',$item->reference);
  $this->assertNull($item->fresh()->user_id);$this->assertGuest();
 }
}
