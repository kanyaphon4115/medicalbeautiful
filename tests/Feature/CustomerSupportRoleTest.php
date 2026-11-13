<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CustomerSupportRoleTest extends TestCase {
 use RefreshDatabase; use \Tests\Concerns\HasApprovedChatBooking;
 public function test_support_role_has_only_customer_support_portal_and_no_hospital_or_admin_access():void {
  $support=User::factory()->create(['role'=>'support']);
  $this->assertTrue($support->isChatSupport());$this->assertFalse($support->isAdmin());$this->assertFalse($support->isHospital());
  $this->assertSame('Customer Support',$support->roleLabel());$this->assertSame('admin.support',$support->landingRoute());
   $this->actingAs($support)->get('/admin/support')->assertOk()->assertViewHas('pages',['chat-management'=>'Chat Management','support'=>'Customer Support','booking-chats'=>'Booking Chats'])->assertViewHas('supportOnly',true);
  foreach(['/admin/dashboard','/admin/payments','/admin/bookings','/admin/accounts','/hospital/dashboard'] as $url)$this->getJson($url)->assertForbidden();
  $this->getJson('/chat/bookings')->assertOk()->assertJsonPath('can_send',false);
  $this->get('/admin/booking-chats')->assertOk();
  $this->postJson('/admin/accounts',['name'=>'Other','email'=>'other@example.test','role'=>'admin','password'=>'TestingPass123!'])->assertForbidden();
 }
 public function test_support_can_read_and_reply_in_real_support_inbox_separately_from_hospital_chat():void {
  $customer=User::factory()->create();$this->approvedChatBooking($customer,false);$support=User::factory()->create(['role'=>'support']);
  $this->actingAs($customer)->postJson('/chat/messages',['channel'=>'support','body'=>'Need support'])->assertCreated();
  $this->actingAs($support)->getJson('/chat/messages')->assertOk()->assertJsonCount(1,'conversations');
  $this->postJson('/chat/messages',['channel'=>'support','customer_id'=>$customer->id,'body'=>'Support reply'])->assertCreated()->assertJsonPath('message.sender_type','support');
  $this->actingAs($customer)->getJson('/chat/messages')->assertJsonPath('messages.1.body','Support reply');
 }
 public function test_admin_can_assign_support_role_and_public_mass_assignment_cannot():void {
  $admin=User::factory()->create(['role'=>'admin']);$account=User::factory()->create();
  $this->actingAs($admin)->patchJson('/admin/accounts/'.$account->id,['name'=>$account->name,'email'=>$account->email,'role'=>'support'])->assertOk()->assertJsonPath('account.role','support');
  $this->assertTrue($account->fresh()->isChatSupport());$this->assertFalse($account->fresh()->isAdmin());
  $public=User::create(['name'=>'Not Support','email'=>'public@example.test','password'=>'TestingPass123!','role'=>'support']);
  $this->assertFalse($public->fresh()->isChatSupport());
 }
}
