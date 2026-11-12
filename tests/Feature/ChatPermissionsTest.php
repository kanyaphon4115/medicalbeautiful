<?php
namespace Tests\Feature;
use App\Models\{User,Booking,Quotation,TreatmentRequest,ChatMessage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Gate,DB,Notification};
use Illuminate\Support\Str;
use Tests\TestCase;
class ChatPermissionsTest extends TestCase {
 use RefreshDatabase;
 private function fixture():array {
  $customer=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);$support=User::factory()->create(['role'=>'support']);$admin=User::factory()->create(['role'=>'admin']);
  $item=TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>$customer->id,'name'=>$customer->name,'email'=>$customer->email,'procedure'=>'Eye Surgery','status'=>'confirmed','selected_hospital_id'=>$hospital->id,'selected_hospital_name'=>$hospital->name,'hospital_approved_at'=>now(),'payment_confirmed_at'=>now(),'consented_at'=>now(),'consent_text'=>'Test consent']);
  $quote=Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>650000,'details'=>'Private details','valid_until'=>today()->addMonth()]);
  $booking=Booking::create(['treatment_request_id'=>$item->id,'quotation_id'=>$quote->id,'hospital_id'=>$hospital->id,'user_id'=>$customer->id,'deposit_phase'=>'treatment','deposit_status'=>'Paid','deposit_amount_satang'=>6000000,'payment_verified_at'=>now(),'status'=>'Confirmed']);
  $supportMessage=ChatMessage::create(['user_id'=>$customer->id,'channel'=>'support','body'=>'PRIVATE-SUPPORT-DETAILS','sender_type'=>'customer']);
  $hospitalMessage=ChatMessage::create(['user_id'=>$customer->id,'channel'=>'hospital','treatment_request_id'=>$item->id,'body'=>'PRIVATE-HOSPITAL-DETAILS','sender_type'=>'customer']);
  return compact('customer','hospital','support','admin','item','booking','supportMessage','hospitalMessage');
 }
 public function test_role_matrix_applies_to_messages_attachments_notifications_and_mutations():void {
  $f=$this->fixture();$outsider=User::factory()->create();$otherHospital=User::factory()->create(['role'=>'hospital']);
  foreach(['customer'=>[true,true],'hospital'=>[false,true],'support'=>[true,true],'admin'=>[true,true]] as $role=>[$canSupport,$canHospital]){
   $actor=$f[$role];$gate=Gate::forUser($actor);
   foreach(['view','viewAttachment','receiveNotification'] as $ability){
    $this->assertSame($canSupport,$gate->allows($ability,$f['supportMessage']),$role.' '.$ability.' support');
    $this->assertSame($canHospital,$gate->allows($ability,$f['hospitalMessage']),$role.' '.$ability.' hospital');
   }
   foreach(['update','delete'] as $ability){$this->assertFalse($gate->allows($ability,$f['supportMessage']));$this->assertFalse($gate->allows($ability,$f['hospitalMessage']));}
  }
  foreach([$outsider,$otherHospital] as $actor)foreach(['view','viewAttachment','receiveNotification'] as $ability){
   $this->assertFalse(Gate::forUser($actor)->allows($ability,$f['supportMessage']));$this->assertFalse(Gate::forUser($actor)->allows($ability,$f['hospitalMessage']));
  }
 }
 public function test_hospital_cannot_read_search_reply_acknowledge_or_upload_into_support():void {
  Notification::fake();$f=$this->fixture();$this->actingAs($f['hospital']);$id=$f['customer']->id;
  foreach(['/chat/messages?channel=support','/chat/messages?channel=support&customer_id='.$id.'&search=PRIVATE','/admin/support','/chat/messages?channel=support&user_id='.$id] as $url)$this->getJson($url)->assertForbidden();
  $this->postJson('/chat/messages',['channel'=>'support','customer_id'=>$id,'body'=>'Unauthorized'])->assertForbidden();
  $this->postJson('/chat/read',['customer_id'=>$id,'last_message_id'=>$f['supportMessage']->id])->assertForbidden();
  $this->post('/chat/messages',['channel'=>'support','customer_id'=>$id,'body'=>'Unauthorized','attachment'=>UploadedFile::fake()->create('private.pdf',1)],['Accept'=>'application/json'])->assertForbidden();
  $this->getJson('/chat/attachments/'.$f['supportMessage']->id)->assertNotFound();
  $this->assertDatabaseCount('chat_messages',2);$this->assertDatabaseCount('chat_reads',0);Notification::assertNothingSent();
  $this->assertDatabaseHas('chat_access_logs',['actor_id'=>$f['hospital']->id,'conversation_type'=>'support','permitted'=>false]);
 }
 public function test_support_and_super_admin_monitor_hospital_history_without_writing_or_acknowledging_messages():void {
  $f=$this->fixture();$original=$f['hospitalMessage']->fresh()->getRawOriginal();
  foreach(['support','admin'] as $role){
   $this->actingAs($f[$role])->getJson('/chat/bookings')->assertOk()->assertJsonPath('can_send',false)->assertJsonPath('rooms.0.can_view',true);
   $this->getJson(route('chat.bookings.messages',$f['booking']))->assertOk()->assertJsonPath('messages.0.body','PRIVATE-HOSPITAL-DETAILS')->assertDontSee('PRIVATE-SUPPORT-DETAILS');
   $this->getJson('/chat/messages?channel=hospital&treatment_request_id='.$f['item']->id)->assertOk()->assertJsonPath('can_send',false)->assertJsonCount(1,'messages');
   $this->postJson(route('chat.bookings.store',$f['booking']),['body'=>'Cannot reply'])->assertForbidden();
   $this->postJson('/chat/messages',['channel'=>'hospital','treatment_request_id'=>$f['item']->id,'body'=>'Cannot reply'])->assertForbidden();
   $this->patchJson('/chat/messages/'.$f['hospitalMessage']->id,['body'=>'Cannot edit'])->assertNotFound();
   $this->get('/admin/booking-chats')->assertOk();
   $this->assertDatabaseHas('chat_access_logs',['actor_id'=>$f[$role]->id,'conversation_type'=>'hospital','action'=>'view_conversation','customer_id'=>$f['customer']->id,'booking_id'=>$f['booking']->id,'permitted'=>true]);
  }
  $this->assertSame($original,$f['hospitalMessage']->fresh()->getRawOriginal());$this->assertDatabaseCount('chat_messages',2);$this->assertDatabaseCount('chat_reads',0);
  $audit=json_encode(DB::table('chat_access_logs')->get());$this->assertStringNotContainsString('PRIVATE-',$audit);$this->assertStringNotContainsString($f['customer']->email,$audit);
 }
 public function test_closed_history_remains_read_only_for_authorized_monitors():void {
  $f=$this->fixture();$f['booking']->update(['status'=>'Cancelled']);
  $this->actingAs($f['support'])->getJson('/chat/bookings')->assertJsonPath('rooms.0.unlocked',false)->assertJsonPath('rooms.0.can_view',true)->assertJsonPath('can_send',false);
  $this->getJson(route('chat.bookings.messages',$f['booking']))->assertOk()->assertJsonCount(1,'messages');
  $this->postJson(route('chat.bookings.store',$f['booking']),['body'=>'Cannot change history'])->assertForbidden();
 }
 public function test_customers_are_isolated_and_channels_cannot_be_cross_linked():void {
  $f=$this->fixture();$outsider=User::factory()->create();$this->actingAs($outsider);
  $this->getJson('/chat/messages?channel=support&customer_id='.$f['customer']->id)->assertForbidden();
  $this->getJson('/chat/messages?channel=hospital&treatment_request_id='.$f['item']->id)->assertNotFound();
  $this->postJson('/chat/messages',['channel'=>'hospital','treatment_request_id'=>$f['item']->id,'body'=>'Cannot impersonate'])->assertNotFound();
  $this->getJson('/chat/messages?channel=support')->assertOk()->assertJsonCount(0,'messages');
  $this->actingAs($f['customer'])->postJson('/chat/messages',['channel'=>'support','treatment_request_id'=>$f['item']->id,'body'=>'Cross-link'])->assertUnprocessable();
  $this->getJson('/chat/messages?channel=support&treatment_request_id='.$f['item']->id)->assertUnprocessable();
  $this->post('/chat/messages',['channel'=>'support','body'=>'Unsupported file','attachment'=>UploadedFile::fake()->create('private.pdf',1)],['Accept'=>'application/json'])->assertUnprocessable();
  $this->assertDatabaseCount('chat_messages',2);
 }
 public function test_selected_hospital_and_customer_can_still_send_to_their_hospital_room():void {
  $f=$this->fixture();
  $this->actingAs($f['hospital'])->postJson(route('chat.bookings.store',$f['booking']),['body'=>'Hospital response'])->assertCreated()->assertJsonPath('message.sender_type','hospital');
  $this->getJson('/chat/messages?channel=hospital&treatment_request_id='.$f['item']->id)->assertOk()->assertJsonPath('can_send',true)->assertJsonCount(2,'messages');
  $this->actingAs($f['customer'])->postJson('/chat/messages',['channel'=>'hospital','treatment_request_id'=>$f['item']->id,'body'=>'Customer response'])->assertCreated();
  $this->getJson('/chat/messages?channel=support')->assertJsonCount(1,'messages')->assertDontSee('Hospital response');
 }
}
