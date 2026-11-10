<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ChatSessionTest extends TestCase {
 use RefreshDatabase;
 public function test_session_refresh_requires_login_and_is_not_cached(): void {
  $this->getJson('/chat/session')->assertUnauthorized();
  $user=User::factory()->create();
  $this->actingAs($user)->withSession(['_token'=>'fresh-token'])->getJson('/chat/session')
   ->assertOk()->assertJsonPath('user_id',$user->id)->assertJsonPath('csrf_token','fresh-token')
   ->assertHeader('Cache-Control','no-store, private');
 }
 public function test_real_csrf_middleware_rejects_stale_token_and_accepts_fresh_token(): void {
  $user=User::factory()->create();$this->actingAs($user);
  // Enable real CSRF verification for this test rather than Laravel's normal test bypass.
  $this->app->instance('env','local');
  $payload=['channel'=>'support','body'=>'CSRF regression test'];
  $this->withSession(['_token'=>'server-token'])->postJson('/chat/messages',$payload,['X-CSRF-TOKEN'=>'stale-token'])->assertStatus(419);
  $this->assertDatabaseCount('chat_messages',0);
  $token=$this->getJson('/chat/session')->assertOk()->json('csrf_token');
  $this->postJson('/chat/messages',$payload,['X-CSRF-TOKEN'=>$token])->assertCreated();
  $this->assertDatabaseCount('chat_messages',1);
 }
}