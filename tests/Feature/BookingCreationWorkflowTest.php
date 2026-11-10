<?php
namespace Tests\Feature;
use App\Models\{User,TreatmentRequest,Quotation,Booking};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BookingCreationWorkflowTest extends TestCase {
 use RefreshDatabase;
 public function test_only_customer_acceptance_creates_one_visible_booking(): void {
  $owner=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);$admin=User::factory()->create(['role'=>'admin']);
  $this->actingAs($owner)->postJson('/requests',['name'=>'Request Owner','email'=>$owner->email,'procedures'=>['Eye Surgery'],'consent_to_share'=>true])->assertCreated();
  $this->assertDatabaseCount('treatment_requests',1);$this->assertDatabaseCount('bookings',0);
  $item=TreatmentRequest::firstOrFail();
  $this->actingAs($hospital)->post(route('hospital.quote',$item),['amount'=>60000,'details'=>'Treatment plan','valid_until'=>today()->addDays(5)->format('Y-m-d')])->assertRedirect();
  $quote=Quotation::firstOrFail();$this->assertSame('quotes_received',$item->fresh()->status);$this->assertDatabaseCount('bookings',0);
  $this->actingAs($admin)->get('/admin/bookings')->assertViewHas('realBookings',fn($rows)=>$rows->total()===0);
  $this->get('/admin/payments')->assertViewHas('realBookings',fn($rows)=>$rows->total()===0);
  $this->get('/admin/requests')->assertSee('Request Owner')->assertSee('Quotes Received');
  $this->actingAs($owner)->postJson(route('quotations.accept',$quote),['expected_total_satang'=>6000000])->assertCreated()->assertJsonPath('booking_status','Pending Payment')->assertJsonPath('payment_status','Pending Payment')->assertJsonPath('deposit_satang',500000)->assertJsonPath('integration.available',false);
  $this->postJson(route('quotations.accept',$quote),['expected_total_satang'=>6000000])->assertUnprocessable();
  $this->assertDatabaseCount('bookings',1);
  $this->get('/my-requests')->assertOk();$this->get('/my-requests')->assertOk();$this->assertDatabaseCount('bookings',1);
  $this->actingAs($admin)->get('/admin/bookings')->assertViewHas('realBookings',fn($rows)=>$rows->total()===1);
  $this->get('/admin/payments')->assertViewHas('realBookings',fn($rows)=>$rows->total()===1);
  $this->get('/admin/requests')->assertSee('Pending Payment');
  $this->actingAs($hospital)->get('/hospital/dashboard')->assertViewHas('bookings',fn($rows)=>$rows->count()===1);
 }
 public function test_dormant_legacy_row_is_preserved_but_hidden_until_explicit_acceptance(): void {
  $owner=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);$admin=User::factory()->create(['role'=>'admin']);
  $this->actingAs($owner)->postJson('/requests',['name'=>'Legacy Owner','email'=>$owner->email,'procedures'=>['Eye Surgery'],'consent_to_share'=>true]);$item=TreatmentRequest::firstOrFail();
  $this->actingAs($hospital)->post(route('hospital.quote',$item),['amount'=>60000,'details'=>'Preserved quote','valid_until'=>today()->addDays(5)->format('Y-m-d')]);$quote=Quotation::firstOrFail();
  $legacy=Booking::create(['treatment_request_id'=>$item->id,'quotation_id'=>$quote->id,'hospital_id'=>$hospital->id,'user_id'=>$owner->id,'status'=>'Awaiting Acceptance','deposit_status'=>'Pending']);
  $before=$legacy->fresh()->getRawOriginal();
  $this->actingAs($admin)->get('/admin/bookings')->assertViewHas('realBookings',fn($rows)=>$rows->isEmpty());
  $this->get('/admin/payments')->assertViewHas('realBookings',fn($rows)=>$rows->isEmpty());
  $this->get('/admin/requests')->assertSee('Legacy Owner')->assertSee('Quotes Received');
  $this->actingAs($hospital)->get('/hospital/dashboard')->assertViewHas('bookings',fn($rows)=>$rows->isEmpty());
  $this->assertSame($before,$legacy->fresh()->getRawOriginal());$this->assertDatabaseCount('bookings',1);
  $this->actingAs($owner)->postJson(route('quotations.accept',$quote),[])->assertCreated();
  $this->assertDatabaseCount('bookings',1);$this->assertSame($legacy->id,Booking::first()->id);$this->assertSame('Pending Payment',$legacy->fresh()->status);
  $this->assertSame('Preserved quote',$quote->fresh()->details);
 }
}
