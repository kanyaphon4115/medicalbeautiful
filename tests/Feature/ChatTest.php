<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\ChatMessage;
use App\Models\TreatmentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class ChatTest extends TestCase {
 use RefreshDatabase; use \Tests\Concerns\HasApprovedChatBooking;
 private function requestFor(User $user): TreatmentRequest {return TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>$user->id,'name'=>'Chat Test','email'=>$user->email,'procedure'=>'Eye Surgery','status'=>'pending','consented_at'=>now(),'consent_text'=>'Test consent']);}
 public function test_guests_cannot_read_or_send_messages(): void {$this->getJson('/chat/messages')->assertUnauthorized();$this->postJson('/chat/messages',['channel'=>'support','body'=>'Hello'])->assertUnauthorized();}
 public function test_support_requires_initial_approval_then_remains_private(): void {
  $user=User::factory()->create();$other=User::factory()->create();
  $this->actingAs($user)->postJson('/chat/messages',['channel'=>'support','body'=>'Before payment'])->assertForbidden();
  $this->approvedChatBooking($user,false);$this->approvedChatBooking($other,false);$this->actingAs($user)->postJson('/chat/messages',['channel'=>'support','body'=>'Please help with my request','user_id'=>$other->id,'sender_type'=>'support'])->assertCreated();
  $message=ChatMessage::firstOrFail();$this->assertSame($user->id,$message->user_id);$this->assertSame('customer',$message->sender_type);$this->assertNotSame('Please help with my request',DB::table('chat_messages')->value('body'));
  $this->getJson('/chat/messages')->assertOk()->assertJsonCount(1,'messages')->assertJsonCount(0,'eligible_requests');
  $this->actingAs($other)->getJson('/chat/messages')->assertOk()->assertJsonCount(0,'messages');
 }
 public function test_hospital_requires_all_conditions_on_same_owned_request(): void {
  $user=User::factory()->create();$item=$this->requestFor($user);$this->actingAs($user);
  $payload=['channel'=>'hospital','body'=>'Hello hospital','treatment_request_id'=>$item->id];
  $this->postJson('/chat/messages',$payload)->assertForbidden();
  $item->selected_hospital_name='Approved Hospital';$item->hospital_approved_at=now();$item->save();
  $this->postJson('/chat/messages',$payload+['payment_confirmed_at'=>now()->toIso8601String()])->assertForbidden();
  $item->payment_confirmed_at=now();$item->save();
  $this->postJson('/chat/messages',$payload)->assertForbidden();$this->approvedChatBooking($user,true,$item);
  $this->postJson('/chat/messages',$payload)->assertCreated();$this->getJson('/chat/messages?channel=hospital&treatment_request_id='.$item->id)->assertOk()->assertJsonCount(1,'messages');
  $this->getJson('/chat/messages')->assertJsonCount(1,'eligible_requests')->assertJsonCount(0,'messages');
  $item->status='cancelled';$item->save();$this->postJson('/chat/messages',$payload)->assertForbidden();
 }
 public function test_other_users_cannot_use_an_eligible_request(): void {
  $owner=User::factory()->create();$item=$this->requestFor($owner);$item->selected_hospital_name='Approved Hospital';$item->hospital_approved_at=now();$item->payment_confirmed_at=now();$item->save();
  $this->actingAs(User::factory()->create())->postJson('/chat/messages',['channel'=>'hospital','treatment_request_id'=>$item->id,'body'=>'Not my request'])->assertNotFound();
  $this->getJson('/chat/messages?channel=hospital&treatment_request_id='.$item->id)->assertNotFound();
 }
 public function test_invalid_messages_and_channels_are_rejected(): void {
  $user=User::factory()->create();$this->approvedChatBooking($user,false);$this->actingAs($user)->postJson('/chat/messages',['channel'=>'unknown','body'=>''])->assertUnprocessable();
  $this->postJson('/chat/messages',['channel'=>'support','body'=>str_repeat('a',2001)])->assertUnprocessable();$this->assertDatabaseCount('chat_messages',0);
 }
 public function test_saved_message_is_returned_immediately_and_new_team_messages_are_available_on_next_read(): void {
  $user=User::factory()->create();$this->approvedChatBooking($user,false);
  $sent=$this->actingAs($user)->postJson('/chat/messages',['channel'=>'support','body'=>'Hello live chat'])
   ->assertCreated()->assertJsonPath('message.body','Hello live chat')->assertJsonPath('message.sender_type','customer')->assertJsonStructure(['message'=>['id','sent_at']]);
  $reply=ChatMessage::create(['user_id'=>$user->id,'channel'=>'support','sender_type'=>'support','body'=>'New reply']);
  $this->getJson('/chat/messages')->assertOk()->assertJsonCount(2,'messages')->assertJsonPath('messages.0.id',$sent->json('id'))->assertJsonPath('messages.1.id',$reply->id)->assertJsonPath('messages.1.body','New reply');
 }
}
