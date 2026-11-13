<?php
namespace Tests\Feature;
use App\Models\{User,Booking,TreatmentRequest,Quotation,ChatMessage};
use App\Notifications\GuestJourneyCode;
use App\Services\PaymentLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth,Notification,Gate};
use Illuminate\Support\Str;
use Tests\TestCase;
class ChatUnlockWorkflowTest extends TestCase {
 use RefreshDatabase; use \Tests\Concerns\HasApprovedChatBooking;
 public function test_real_initial_approval_otp_activation_and_booking_deposit_approval_control_chat_access():void {
  Notification::fake();$password='BookingChatTest123!';
  $owner=User::factory()->create(['email_verified_at'=>null,'password'=>$password]);$hospital=User::factory()->create(['role'=>'hospital']);$admin=User::factory()->create(['role'=>'admin']);$support=User::factory()->create(['role'=>'support']);
  $item=TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>null,'name'=>$owner->name,'email'=>$owner->email,'procedure'=>'Eye Surgery','status'=>'pending_payment','selected_hospital_id'=>$hospital->id,'selected_hospital_name'=>$hospital->name,'hospital_approved_at'=>now(),'consented_at'=>now(),'consent_text'=>'Test consent']);
  $quote=Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>650000,'details'=>'Treatment details','valid_until'=>today()->addMonth()]);
  $booking=Booking::create(['treatment_request_id'=>$item->id,'quotation_id'=>$quote->id,'hospital_id'=>$hospital->id,'user_id'=>null,'status'=>'Pending Payment','deposit_status'=>'Pending','deposit_amount_satang'=>500000]);
  $initial=(new PaymentLedger)->ensureCurrent($booking);
  $this->actingAs($owner)->getJson(route('chat.access'))->assertOk()->assertJsonPath('support_available',false)->assertJsonCount(0,'hospital_requests');
  $this->getJson('/chat/messages?channel=support')->assertForbidden();$this->postJson('/chat/messages',['channel'=>'support','body'=>'Before approval'])->assertForbidden();
  $hospitalUrl='/chat/messages?channel=hospital&treatment_request_id='.$item->id;
  $this->getJson($hospitalUrl)->assertNotFound();
  $this->actingAs($admin)->post(route('admin.payments.confirm-transaction',$initial),['confirmation'=>1,'funds_verified'=>1])->assertRedirect();
  $this->actingAs($owner)->getJson(route('chat.access'))->assertJsonPath('support_available',false);
  $progress=app(\App\Services\BookingChatAccess::class)->progress($booking->fresh());$this->assertTrue($progress['step4_approved']);$this->assertFalse($progress['account_activated']);$code=null;
  Notification::assertSentOnDemand(GuestJourneyCode::class,function($n)use(&$code){$code=$n->code;return true;});
  $this->withSession(['real_guest'=>[$item->reference=>now()->addDay()->timestamp]])->postJson(route('guest.requests.verify',$item->reference),['code'=>$code])->assertOk();Auth::logout();
  $this->post(route('login.store'),['email'=>$owner->email,'password'=>$password])->assertRedirect(route('requests.index'));
  $this->assertNotNull($owner->fresh()->email_verified_at);
  $this->getJson(route('chat.access'))->assertJsonPath('support_available',true)->assertJsonPath('hospital_requests.0.step4_approved',true)->assertJsonPath('hospital_requests.0.step9_approved',false)->assertJsonPath('hospital_requests.0.unlocked',false);
  $this->postJson('/chat/messages',['channel'=>'support','body'=>'Support after activation'])->assertCreated();$this->getJson($hospitalUrl)->assertForbidden();
  $this->postJson(route('bookings.treatment-deposit',$booking),['confirm_treatment'=>1])->assertOk();
  $this->post(route('guest.requests.payment-claim',$item->reference),[])->assertRedirect();
  $stepNine=$booking->paymentSubmissions()->where('purpose','treatment')->firstOrFail();
  $this->assertSame('Pending Verification',$booking->fresh()->deposit_status);$this->getJson($hospitalUrl)->assertForbidden();
  $this->getJson(route('chat.access'))->assertJsonPath('support_available',true)->assertJsonPath('hospital_requests.0.step9_approved',false)->assertJsonPath('hospital_requests.0.deposit_url',route('requests.index').'#booking-deposit-'.$booking->id);
  $this->actingAs($support)->getJson(route('chat.bookings.messages',$booking))->assertForbidden();
  $this->actingAs($admin)->post(route('admin.payments.confirm-transaction',$stepNine),['confirmation'=>1,'funds_verified'=>1])->assertRedirect();
  $this->assertSame('Confirmed',$booking->fresh()->status);
  $this->actingAs($owner)->getJson(route('chat.access'))->assertJsonPath('support_available',true)->assertJsonPath('hospital_requests.0.unlocked',true)->assertJsonPath('hospital_requests.0.step9_approved',true);
  $this->postJson('/chat/messages',['channel'=>'hospital','treatment_request_id'=>$item->id,'body'=>'Unlocked hospital message'])->assertCreated();
  $this->actingAs($hospital)->getJson(route('chat.bookings.messages',$booking))->assertOk()->assertJsonCount(1,'messages');
  $this->postJson(route('chat.bookings.store',$booking),['body'=>'Hospital response'])->assertCreated();$this->getJson('/chat/messages?channel=support')->assertForbidden();
  $this->actingAs($support)->getJson(route('chat.bookings.messages',$booking))->assertOk()->assertJsonCount(2,'messages');
  $this->postJson(route('chat.bookings.store',$booking),['body'=>'Read-only'])->assertForbidden();
  $message=ChatMessage::where('channel','hospital')->firstOrFail();
  $stepNine->update(['status'=>'rejected']);
  $this->actingAs($owner)->getJson($hospitalUrl)->assertForbidden();$this->getJson('/chat/messages?channel=support')->assertOk();
  foreach([$owner,$hospital,$support,$admin] as $actor){$this->assertFalse(Gate::forUser($actor)->allows('viewAttachment',$message));$this->assertFalse(Gate::forUser($actor)->allows('receiveNotification',$message));}
  $stepNine->update(['status'=>'approved']);$booking->update(['status'=>'Cancelled']);
  $this->getJson($hospitalUrl)->assertForbidden();$this->getJson('/chat/messages?channel=support')->assertOk();
  $initial->update(['status'=>'rejected']);$this->getJson('/chat/messages?channel=support')->assertForbidden();
  $this->assertDatabaseCount('chat_messages',3);
 }
 public function test_revoked_activation_blocks_existing_hospital_history_and_both_message_endpoints():void {
  $owner=User::factory()->create();
  $booking=$this->approvedChatBooking($owner);
  $message=ChatMessage::create(['user_id'=>$owner->id,'channel'=>'hospital','treatment_request_id'=>$booking->treatment_request_id,'sender_type'=>'customer','body'=>'Retained history']);
  $owner->forceFill(['email_verified_at'=>null])->save();
  foreach([$owner,$booking->hospital,User::factory()->create(['role'=>'support'])] as $actor){
   $this->actingAs($actor)->getJson('/chat/messages?channel=hospital&treatment_request_id='.$booking->treatment_request_id)->assertForbidden();
   $this->postJson('/chat/messages',['channel'=>'hospital','treatment_request_id'=>$booking->treatment_request_id,'body'=>'Blocked'])->assertForbidden();
   $this->assertFalse(Gate::forUser($actor)->allows('viewAttachment',$message));
   if($actor->id!==$owner->id){
    $this->getJson(route('chat.bookings.messages',$booking))->assertForbidden();
    $this->postJson(route('chat.bookings.store',$booking),['body'=>'Blocked'])->assertForbidden();
   }
  }
  $this->actingAs($owner)->getJson('/chat/messages?channel=support')->assertForbidden();
  $this->assertDatabaseCount('chat_messages',1);
 }
 public function test_account_and_payment_flags_alone_cannot_unlock_chat_without_reviewed_transactions():void {
  $owner=User::factory()->create();$this->actingAs($owner)->get('/my-requests')->assertOk()->assertSee('Hospital Chat Locked')->assertSee('View Booking Deposit');
  $this->getJson(route('chat.access'))->assertJsonPath('support_available',false)->assertJsonCount(0,'hospital_requests');
  $this->postJson('/chat/messages',['channel'=>'support','body'=>'Cannot bypass','deposit_status'=>'Paid','email_verified_at'=>now()])->assertForbidden();
 }
}
