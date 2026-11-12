<?php
namespace Tests\Feature;
use App\Models\{User,TreatmentRequest,Quotation,Booking};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentEligibilityTest extends TestCase {
 use RefreshDatabase;
 private function booking(bool $accepted,string $status='Pending Payment',string $deposit='Pending'): Booking {
  $owner=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);
  $r=TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>$owner->id,'name'=>$owner->name,'email'=>$owner->email,
   'procedure'=>'Eye Surgery','status'=>'quotes_received','selected_hospital_id'=>$accepted?$hospital->id:null,
   'hospital_approved_at'=>$accepted?now():null,'consented_at'=>now(),'consent_text'=>'Consent']);
  $q=Quotation::create(['treatment_request_id'=>$r->id,'hospital_id'=>$hospital->id,'amount'=>60000,'details'=>'Offer','valid_until'=>today()->addDays(5)]);
  return Booking::create(['treatment_request_id'=>$r->id,'quotation_id'=>$q->id,'hospital_id'=>$hospital->id,'user_id'=>$owner->id,
   'status'=>$status,'deposit_status'=>$deposit,'deposit_amount_satang'=>500000]);
 }
 public function test_quotes_received_bookings_are_not_payments_and_reads_do_not_change_statuses(): void {
  $reset=$this->booking(false,'Awaiting Acceptance');$orphan=$this->booking(false);$pending=$this->booking(true);
  $this->assertSame('Not Started',$reset->payment_status);$this->assertSame('Not Started',$orphan->payment_status);$this->assertSame('Pending Payment',$pending->payment_status);
  $before=[$reset->fresh()->getRawOriginal(),$orphan->fresh()->getRawOriginal(),$pending->fresh()->getRawOriginal()];
  $this->actingAs(User::factory()->create(['role'=>'admin']))->get('/admin/payments')->assertOk()
   ->assertViewHas('realBookings',fn($rows)=>$rows->total()===1 && $rows->first()->id===$pending->id)
   ->assertViewHas('paymentTotals',fn($totals)=>$totals['pending']===1);
  $this->get('/admin/bookings')->assertOk()->assertDontSee('Not Started')->assertViewHas('realBookings',fn($rows)=>$rows->total()===1);
  $this->get('/admin/payments?status=Pending%20Payment&size=5')->assertViewHas('realBookings',fn($rows)=>$rows->total()===1);
  $this->get('/admin/payments?search='.urlencode($reset->reference))->assertViewHas('realBookings',fn($rows)=>$rows->isEmpty());
  foreach([$reset,$orphan] as $b){$this->assertSame('quotes_received',$b->treatmentRequest->customer_status);$this->actingAs($b->customer)->get('/my-requests')->assertViewHas('requests',fn($rows)=>$rows->first()['status']==='quotes_received');}
  $this->assertSame('pending',$pending->treatmentRequest->customer_status);
  $this->assertSame($before,[$reset->fresh()->getRawOriginal(),$orphan->fresh()->getRawOriginal(),$pending->fresh()->getRawOriginal()]);
  $this->assertSame('quotes_received',$pending->treatmentRequest->fresh()->status);
 }
 public function test_paid_refunded_and_verified_history_remain_visible_without_current_selection(): void {
  $paid=$this->booking(false,'Confirmed','Paid');$refund=$this->booking(false,'Confirmed','Refunded');$refund->update(['refunded_amount_satang'=>500000]);
  $history=$this->booking(false,'Awaiting Acceptance');$history->update(['payment_verified_at'=>now()]);
  $this->actingAs(User::factory()->create(['role'=>'admin']))->get('/admin/payments')->assertOk()
   ->assertViewHas('realBookings',fn($rows)=>$rows->total()===3)
   ->assertViewHas('paymentTotals',fn($totals)=>(int)$totals['received']===500000 && $totals['paid']===1 && $totals['pending']===0 && (int)$totals['refunded']===500000);
  $this->get('/admin/payments?status=Paid')->assertViewHas('realBookings',fn($rows)=>$rows->total()===1 && $rows->first()->id===$paid->id);
  $this->assertSame('Paid',$paid->payment_status);$this->assertSame('Refunded',$refund->payment_status);
  $paid->update(['status'=>'Awaiting Acceptance']);$this->assertSame('Paid',$paid->fresh()->payment_status);
  $this->assertSame('account_activated',$paid->treatmentRequest->customer_status);
 }
 public function test_confirmed_unpaid_requires_acceptance_and_does_not_mean_paid(): void {
  $valid=$this->booking(true,'Confirmed');$invalid=$this->booking(false,'Confirmed');
  $this->assertSame('pending',$valid->treatmentRequest->customer_status);$this->assertSame('quotes_received',$invalid->treatmentRequest->customer_status);
  $this->actingAs(User::factory()->create(['role'=>'admin']))->get('/admin/payments?status=Pending%20Payment')
   ->assertViewHas('realBookings',fn($rows)=>$rows->total()===1 && $rows->first()->id===$valid->id)
   ->assertViewHas('paymentTotals',fn($totals)=>$totals['pending']===1);
 }
}
