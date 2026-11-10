<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\ChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_counts_persist_per_admin_and_only_acknowledge_displayed_messages(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($customer);
        $first = $this->postJson('/chat/messages', ['channel'=>'support', 'body'=>'First'])->json('id');
        $this->postJson('/chat/messages', ['channel'=>'support', 'body'=>'Second'])->assertCreated();
        $this->postJson('/chat/read', ['customer_id'=>$customer->id, 'last_message_id'=>$first])->assertForbidden();
        $this->actingAs($admin)->getJson('/chat/messages')->assertJsonPath('conversations.0.unread_count', 2);
        $this->postJson('/chat/read', ['customer_id'=>$customer->id, 'last_message_id'=>$first])->assertOk();
        $this->getJson('/chat/messages')->assertJsonPath('conversations.0.unread_count', 1);
        $reply = $this->postJson('/chat/messages', ['channel'=>'support', 'customer_id'=>$customer->id, 'body'=>'Reply'])->json('id');
        $this->getJson('/chat/messages')->assertJsonPath('conversations.0.unread_count', 1);
        $this->postJson('/chat/read', ['customer_id'=>$customer->id, 'last_message_id'=>$reply])->assertOk();
        $this->postJson('/chat/read', ['customer_id'=>$customer->id, 'last_message_id'=>$first])->assertOk();
        $this->getJson('/chat/messages')->assertJsonPath('conversations.0.unread_count', 0);
        $this->postJson('/chat/read', ['customer_id'=>$other->id, 'last_message_id'=>$first])->assertNotFound();
        $this->actingAs($other)->getJson('/chat/messages')->assertJsonPath('conversations.0.unread_count', 2);
    }

    public function test_two_different_accounts_can_exchange_private_support_messages(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->create();
        $unrelated = User::factory()->create();
        $staff->forceFill(['role'=>'admin'])->save();
        $this->actingAs($customer)->postJson('/chat/messages',['channel'=>'support','body'=>'Customer question'])->assertCreated();
        $this->actingAs($staff)->getJson('/chat/messages')->assertOk()
            ->assertJsonCount(1,'conversations')->assertJsonPath('conversations.0.id',$customer->id);
        $this->getJson('/chat/messages?customer_id='.$customer->id)->assertOk()
            ->assertJsonPath('messages.0.body','Customer question');
        $this->postJson('/chat/messages',['channel'=>'support','customer_id'=>$customer->id,'body'=>'Staff answer','sender_type'=>'customer'])
            ->assertCreated()->assertJsonPath('message.sender_type','support');
        $this->actingAs($customer)->getJson('/chat/messages')->assertOk()->assertJsonCount(2,'messages')
            ->assertJsonPath('messages.1.body','Staff answer')->assertJsonCount(0,'conversations');
        $this->actingAs($unrelated)->getJson('/chat/messages')->assertOk()->assertJsonCount(0,'messages')
            ->assertJsonCount(0,'conversations');
    }

    public function test_customer_cannot_read_or_reply_to_another_customer(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create(['name'=>'admin']);
        $this->actingAs($owner)->postJson('/chat/messages',['channel'=>'support','body'=>'Private'])->assertCreated();
        $this->actingAs($other)->getJson('/chat/messages?customer_id='.$owner->id)->assertForbidden();
        $this->postJson('/chat/messages',['channel'=>'support','customer_id'=>$owner->id,'body'=>'Unauthorized'])->assertForbidden();
        $this->assertDatabaseCount('chat_messages',1);
    }

    public function test_stale_account_header_cannot_read_or_send_as_current_account(): void
    {
        $original = User::factory()->create();
        $current = User::factory()->create();
        $this->actingAs($current)->withHeader('X-Chat-User-Id',(string) $original->id)
            ->getJson('/chat/messages')->assertStatus(409);
        $this->postJson('/chat/messages',['channel'=>'support','body'=>'Old tab draft'])->assertStatus(409);
        $this->assertDatabaseCount('chat_messages',0);
    }

    public function test_support_cannot_bypass_hospital_ownership_or_reply_to_nonexistent_conversations(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->create();
        $staff->forceFill(['role'=>'admin'])->save();
        $this->actingAs($staff)->postJson('/chat/messages',['channel'=>'support','customer_id'=>$customer->id,'body'=>'No conversation'])->assertNotFound();
        $this->getJson('/chat/messages?channel=hospital&customer_id='.$customer->id.'&treatment_request_id=0')->assertNotFound();
        $this->assertDatabaseCount('chat_messages',0);
    }
}
