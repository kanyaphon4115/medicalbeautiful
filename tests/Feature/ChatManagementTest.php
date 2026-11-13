<?php
namespace Tests\Feature;

use App\Models\{User,ChatMessage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatManagementTest extends TestCase
{
 use RefreshDatabase; use \Tests\Concerns\HasApprovedChatBooking;

 public function test_admin_reads_and_replies_to_the_same_customer_widget_conversation():void
 {
  $customer=User::factory()->create(['name'=>'TestZZO']);
  $booking=$this->approvedChatBooking($customer,false);
  $admin=User::factory()->create(['role'=>'admin']);
  $support=User::factory()->create(['role'=>'support']);
  $id=$this->actingAs($customer)->postJson('/chat/messages',['channel'=>'support','body'=>'hi'])->assertCreated()->json('id');
  $this->assertSame('hi',ChatMessage::findOrFail($id)->body);
  foreach([$admin,$support] as $actor){
   $this->actingAs($actor)->get('/admin/chat-management')->assertOk()->assertSee('Chat Management')->assertSee('Database records')->assertDontSee('class="ap-demo"',false);
   $this->getJson('/chat/messages?channel=support')->assertOk()->assertJsonPath('conversations.0.customer_name','TestZZO')->assertJsonPath('conversations.0.latest_message','hi')->assertJsonPath('conversations.0.unread_count',1)->assertJsonPath('conversations.0.status','Active');
  }
  $this->actingAs($admin)->getJson('/chat/messages?channel=support&customer_id='.$customer->id)->assertJsonPath('messages.0.id',$id);
  $this->postJson('/chat/read',['customer_id'=>$customer->id,'last_message_id'=>$id])->assertOk();
  $this->getJson('/chat/messages?channel=support')->assertJsonPath('conversations.0.unread_count',0);
  $reply=$this->postJson('/chat/messages',['channel'=>'support','customer_id'=>$customer->id,'body'=>'Hello, how can we help you?'])->assertCreated()->json('id');
  for($i=0;$i<2;$i++){
   $this->actingAs($customer)->getJson('/chat/messages?channel=support')->assertJsonCount(2,'messages')->assertJsonPath('messages.0.id',$id)->assertJsonPath('messages.1.id',$reply)->assertJsonPath('messages.1.body','Hello, how can we help you?');
   $this->actingAs($admin)->getJson('/chat/messages?channel=support&customer_id='.$customer->id)->assertJsonCount(2,'messages');
  }
  $this->actingAs($booking->hospital)->get('/admin/chat-management')->assertForbidden();
  $this->getJson('/chat/messages?channel=support&customer_id='.$customer->id)->assertForbidden();
  $this->assertDatabaseCount('chat_messages',2);
 }

 public function test_staff_can_retrieve_complete_support_history_beyond_fifty_messages():void
 {
  $customer=User::factory()->create();$this->approvedChatBooking($customer,false);
  for($i=0;$i<61;$i++)ChatMessage::create(['user_id'=>$customer->id,'channel'=>'support','sender_type'=>'customer','body'=>'Message '.$i]);
  $this->actingAs(User::factory()->create(['role'=>'admin']))->getJson('/chat/messages?channel=support&customer_id='.$customer->id)->assertJsonCount(61,'messages')->assertJsonPath('messages.0.body','Message 0')->assertJsonPath('messages.60.body','Message 60');
 }
}
