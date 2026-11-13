<?php
namespace Tests\Feature;
use App\Models\{Booking,Quotation,TreatmentRequest,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class BookingChatTest extends TestCase {
 use RefreshDatabase;
 private function booking():Booking {
  $owner=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);
  $item=TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>$owner->id,'name'=>$owner->name,'email'=>$owner->email,'procedure'=>'Eye Surgery','status'=>'confirmed','selected_hospital_id'=>$hospital->id,'selected_hospital_name'=>$hospital->name,'hospital_approved_at'=>now(),'payment_confirmed_at'=>now(),'consented_at'=>now(),'consent_text'=>'Test consent']);
  $quote=Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>650000,'details'=>'Treatment details','valid_until'=>today()->addMonth()]);
  $booking=Booking::create(['treatment_request_id'=>$item->id,'quotation_id'=>$quote->id,'hospital_id'=>$hospital->id,'user_id'=>$owner->id,'deposit_phase'=>'treatment','deposit_status'=>'Paid','deposit_amount_satang'=>6000000,'payment_verified_at'=>now(),'status'=>'Confirmed','is_test'=>false,'payment_is_simulated'=>false]);
  $booking->paymentSubmissions()->create(['purpose'=>'initial','status'=>'approved','amount_satang'=>500000,'method'=>'Bank Transfer','submitted_at'=>now(),'reviewed_at'=>now()]);
  $booking->paymentSubmissions()->create(['purpose'=>'treatment','status'=>'approved','amount_satang'=>6000000,'method'=>'Bank Transfer','submitted_at'=>now(),'reviewed_at'=>now()]);
  return $booking;
 }
 private function url(Booking $booking):string{return route('chat.bookings.messages',$booking);}
 public function test_admin_and_hospital_share_real_customer_messages_and_replies():void {
  $booking=$this->booking();$owner=$booking->customer;$hospital=$booking->hospital;$admin=User::factory()->create(['role'=>'support']);
  $this->actingAs($owner)->postJson(route('chat.store'),['channel'=>'hospital','treatment_request_id'=>$booking->treatment_request_id,'body'=>'Customer question'])->assertCreated();
  $this->postJson(route('chat.store'),['channel'=>'support','body'=>'Private support message'])->assertCreated();
  $this->actingAs($hospital)->getJson(route('chat.bookings.index'))->assertOk()->assertJsonCount(1,'rooms')->assertJsonPath('rooms.0.id',$booking->id)->assertJsonPath('rooms.0.unlocked',true);
  $this->getJson($this->url($booking))->assertOk()->assertJsonCount(1,'messages')->assertJsonPath('messages.0.body','Customer question')->assertDontSee('Private support message');
  $this->postJson($this->url($booking),['body'=>'Hospital reply','sender_type'=>'customer','user_id'=>$admin->id])->assertCreated()->assertJsonPath('message.sender_type','hospital');
  $this->actingAs($admin)->getJson($this->url($booking))->assertOk()->assertJsonCount(2,'messages');
  $this->postJson($this->url($booking),['body'=>'Admin reply'])->assertForbidden();
  $this->postJson(route('chat.store'),['channel'=>'support','customer_id'=>$owner->id,'body'=>'Support reply'])->assertCreated();
  $this->actingAs($owner)->getJson(route('chat.index',['channel'=>'hospital','treatment_request_id'=>$booking->treatment_request_id]))->assertOk()->assertJsonCount(2,'messages')->assertJsonPath('messages.1.body','Hospital reply')->assertDontSee('Support reply');
  $this->assertNotSame('Hospital reply',DB::table('chat_messages')->where('sender_type','hospital')->value('body'));
  $this->assertDatabaseHas('chat_messages',['user_id'=>$owner->id,'treatment_request_id'=>$booking->treatment_request_id,'channel'=>'hospital','sender_type'=>'hospital']);
 }
 public function test_hospital_can_only_list_read_and_write_its_own_bookings():void {
  $own=$this->booking();$other=$this->booking();$this->actingAs($own->hospital);
  $this->getJson(route('chat.bookings.index'))->assertOk()->assertJsonCount(1,'rooms')->assertJsonPath('rooms.0.id',$own->id)->assertDontSee($other->customer->email);
  $this->getJson($this->url($other))->assertNotFound();$this->postJson($this->url($other),['body'=>'Unauthorized'])->assertNotFound();
  $this->actingAs(User::factory()->create(['role'=>'support']))->getJson(route('chat.bookings.index'))->assertOk()->assertJsonCount(2,'rooms');$this->assertDatabaseCount('chat_messages',0);
 }
 public function test_locked_cancelled_refunded_and_simulated_rooms_cannot_send_or_read():void {
  foreach(['unpaid','cancelled','refunded','simulated'] as $state){
   $booking=$this->booking();
   if($state==='unpaid')$booking->treatmentRequest->update(['payment_confirmed_at'=>null]);
   if($state==='cancelled')$booking->update(['status'=>'Cancelled']);
   if($state==='refunded')$booking->update(['deposit_status'=>'Refunded']);
   if($state==='simulated')$booking->update(['payment_is_simulated'=>true]);
   $this->actingAs($booking->hospital)->getJson(route('chat.bookings.index'))->assertOk()->assertJsonPath('rooms.0.unlocked',false);
   $this->getJson($this->url($booking))->assertForbidden();$this->postJson($this->url($booking),['body'=>'Locked message'])->assertForbidden();
   $this->actingAs($booking->customer)->postJson(route('chat.store'),['channel'=>'hospital','treatment_request_id'=>$booking->treatment_request_id,'body'=>'Locked message'])->assertForbidden();
  }
  $this->assertDatabaseCount('chat_messages',0);
 }
 public function test_guest_customers_and_stale_sessions_cannot_access_staff_chat_endpoints():void {
  $booking=$this->booking();$this->getJson(route('chat.bookings.index'))->assertUnauthorized();
  $this->actingAs($booking->customer)->getJson(route('chat.bookings.index'))->assertForbidden();$this->getJson($this->url($booking))->assertForbidden();$this->postJson($this->url($booking),['body'=>'No access'])->assertForbidden();
  $this->actingAs($booking->hospital)->withHeader('X-Chat-User-Id',(string)$booking->customer->id)->getJson(route('chat.bookings.index'))->assertConflict();$this->postJson($this->url($booking),['body'=>'Stale account'])->assertConflict();$this->assertDatabaseCount('chat_messages',0);
 }
 public function test_rooms_without_messages_are_empty_and_invalid_messages_are_rejected():void {
  $booking=$this->booking();$this->actingAs($booking->hospital);$this->getJson($this->url($booking))->assertOk()->assertJsonCount(0,'messages');
  $this->postJson($this->url($booking),['body'=>' '])->assertUnprocessable();$this->postJson($this->url($booking),['body'=>str_repeat('a',2001)])->assertUnprocessable();$this->assertDatabaseCount('chat_messages',0);
 }
 public function test_admin_page_and_hospital_dashboard_use_live_booking_chat_configuration():void {
  $booking=$this->booking();$this->actingAs(User::factory()->create(['role'=>'support']))->get(route('admin.booking-chats'))->assertOk()->assertSee('bookingChatEndpoint')->assertDontSee('class="ap-demo"',false)->assertDontSee('Sarah Kim');
  $this->actingAs($booking->hospital)->get(route('hospital.dashboard'))->assertOk()->assertSee('hospital-chat-config')->assertSee('Open Booking Chat')->assertDontSee('Hospital booking chat is not connected yet.');
 }
}
