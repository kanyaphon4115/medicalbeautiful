<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class AccountSettingsTest extends TestCase {
 use RefreshDatabase;
 public function test_settings_require_authentication(): void {
  $this->getJson('/account/settings')->assertUnauthorized();$this->patchJson('/account/settings',[])->assertUnauthorized();$this->postJson('/account/deletion-request',['confirmation'=>'DELETE'])->assertUnauthorized();
 }
 public function test_preferences_persist_only_for_current_user(): void {
  $user=User::factory()->create();$other=User::factory()->create();
  $this->actingAs($user)->patchJson('/account/settings',['user_id'=>$other->id,'role'=>'admin','notifications'=>['requests'=>false,'quotations'=>true,'messages'=>false],'language'=>'th'])->assertOk();
  $this->assertSame(['requests'=>false,'quotations'=>true,'messages'=>false],$user->fresh()->notification_preferences);$this->assertSame('th',$user->fresh()->preferred_language);$this->assertSame('user',$user->fresh()->role);
  $this->assertNull($other->fresh()->notification_preferences);$this->assertSame('en',$other->fresh()->preferred_language);
  $this->getJson('/account/settings')->assertOk()->assertJsonPath('language','th')->assertJsonPath('notifications.requests',false);
 }
 public function test_settings_validate_values_and_show_google_connection_without_identifiers(): void {
  $user=User::factory()->create();$user->google_id='google-test-subject';$user->save();
  $this->actingAs($user)->getJson('/account/settings')->assertOk()->assertJsonPath('google_connected',true)->assertJsonPath('google_email',$user->email)->assertJsonMissingPath('google_id');
  $this->patchJson('/account/settings',['notifications'=>['requests'=>'invalid'],'language'=>'unknown'])->assertUnprocessable();$this->assertSame('en',$user->fresh()->preferred_language);
 }
 public function test_deletion_is_a_cancellable_review_request_and_does_not_delete_data(): void {
  $user=User::factory()->create();$this->actingAs($user)->postJson('/account/deletion-request',['confirmation'=>'wrong'])->assertUnprocessable();
  $this->postJson('/account/deletion-request',['confirmation'=>'DELETE'])->assertOk();$this->assertNotNull($user->fresh()->account_deletion_requested_at);$this->assertDatabaseCount('users',1);
  $this->deleteJson('/account/deletion-request')->assertOk();$this->assertNull($user->fresh()->account_deletion_requested_at);$this->assertAuthenticatedAs($user);
 }
 public function test_signing_out_other_sessions_preserves_current_and_other_users_sessions(): void {
  $user=User::factory()->create();$other=User::factory()->create();$this->actingAs($user)->withSession(['settings_test'=>true]);$current=session()->getId();$this->withCredentials()->withCookie(config('session.cookie'),$current);
  foreach([[$current,$user->id],['other-device',$user->id],['different-user',$other->id]] as [$id,$owner])DB::table('sessions')->insert(['id'=>$id,'user_id'=>$owner,'payload'=>'','last_activity'=>time()]);
  $this->postJson('/account/sessions/sign-out-others')->assertOk();$this->assertDatabaseHas('sessions',['id'=>$current]);$this->assertDatabaseHas('sessions',['id'=>'different-user']);$this->assertDatabaseMissing('sessions',['id'=>'other-device']);$this->assertAuthenticatedAs($user);
 }
}
