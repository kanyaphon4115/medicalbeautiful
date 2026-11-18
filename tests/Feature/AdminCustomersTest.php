<?php
namespace Tests\Feature;
use App\Models\{User,TreatmentRequest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomersTest extends TestCase {
 use RefreshDatabase;use \Tests\Concerns\HasApprovedChatBooking;
 public function test_customers_are_database_records_with_accurate_counts_and_detail_views():void {
  $customer=User::factory()->create(['name'=>'Actual Customer','email'=>'actual@example.test']);$booking=$this->approvedChatBooking($customer);
  $customer->forceFill(['country_code'=>'TH','phone_number'=>'+66812345678'])->save();
  TreatmentRequest::create(['user_id'=>$customer->id,'reference'=>(string)\Illuminate\Support\Str::uuid(),'name'=>'Second request','email'=>$customer->email,'procedure'=>'Rhinoplasty','status'=>'pending','consented_at'=>now(),'consent_text'=>'Test consent']);
  User::factory()->create(['name'=>'Private Hospital','role'=>'hospital']);
  $this->actingAs(User::factory()->create(['role'=>'admin']))->get('/admin/customers')->assertOk()->assertSee('Actual Customer')->assertSee('actual@example.test')->assertSee('Thailand')->assertSee('Database records')->assertDontSee('class="ap-demo"',false)->assertDontSee('Sarah Kim')->assertDontSee('Private Hospital')->assertSee('real-customer-'.$customer->id)->assertSee($booking->reference)
   ->assertViewHas('customers',fn($rows)=>$rows->total()===1&&$rows->first()->treatment_requests_count===2&&$rows->first()->bookings_count===1);
 }
 public function test_search_country_status_and_pagination_query_real_customers():void {
  $active=User::factory()->create(['name'=>'Active Customer']);$active->forceFill(['country_code'=>'TH'])->save();
  $pending=User::factory()->create(['name'=>'Pending Customer','email_verified_at'=>null]);$pending->forceFill(['country_code'=>'KR'])->save();
  $deleting=User::factory()->create(['name'=>'Deleting Customer']);$deleting->forceFill(['account_deletion_requested_at'=>now()])->save();
  $this->actingAs(User::factory()->create(['role'=>'admin']));
  $this->get('/admin/customers?search='.urlencode($active->email))->assertViewHas('customers',fn($rows)=>$rows->total()===1&&$rows->first()->id===$active->id);
  $this->get('/admin/customers?search='.sprintf('C-%03d',$pending->id))->assertViewHas('customers',fn($rows)=>$rows->first()->id===$pending->id);
  $this->get('/admin/customers?country=KR&status=pending')->assertViewHas('customers',fn($rows)=>$rows->total()===1&&$rows->first()->id===$pending->id);
  $this->get('/admin/customers?status=active')->assertViewHas('customers',fn($rows)=>$rows->total()===1);
  $this->get('/admin/customers?status=deletion_requested')->assertViewHas('customers',fn($rows)=>$rows->total()===1&&$rows->first()->id===$deleting->id);
  $this->get('/admin/customers?country=unrecorded')->assertViewHas('customers',fn($rows)=>$rows->total()===1);
  User::factory()->count(8)->create();$this->get('/admin/customers?size=5&page=2')->assertViewHas('customers',fn($rows)=>$rows->count()===5&&$rows->total()===11);
  $this->get('/admin/customers?search=missing-record')->assertSee('No customers found.');
 }
 public function test_customer_data_requires_admin_access():void {
  $this->get('/admin/customers')->assertRedirect(route('login'));
  foreach(['user','support','hospital'] as $role)$this->actingAs(User::factory()->create(['role'=>$role]))->get('/admin/customers')->assertForbidden();
 }
}
