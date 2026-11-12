<?php
namespace Tests\Feature;
use App\Models\{User,TreatmentRequest,Quotation,Booking};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class AdminPaymentTest extends TestCase {
 use RefreshDatabase;
 private function booking(bool $test=true): Booking {
  $owner=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);
  $item=TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>$owner->id,'name'=>$owner->name,'email'=>$owner->email,'procedure'=>'Eye Surgery','status'=>'pending_payment','selected_hospital_id'=>$hospital->id,'selected_hospital_name'=>$hospital->name,'hospital_approved_at'=>now(),'consented_at'=>now(),'consent_text'=>'Consent']);
  $quote=Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>60000,'details'=>'Real database quotation details','valid_until'=>today()->addDays(5)]);
  return Booking::create(['treatment_request_id'=>$item->id,'quotation_id'=>$quote->id,'hospital_id'=>$hospital->id,'user_id'=>$owner->id,'status'=>'Pending Payment','deposit_status'=>'Pending','deposit_amount_satang'=>500000,'is_test'=>$test]);
 }
 public function test_admin_test_verification_is_atomic_audited_and_synchronized(): void {
  $booking=$this->booking();$admin=User::factory()->create(['role'=>'admin']);
  $this->actingAs($admin)->get('/admin/payments')->assertOk()->assertSee('Deposit Amount')->assertSee('THB 5,000.00')->assertDontSee('Sarah Kim');
  $this->post(route('admin.payments.test-verify',$booking),['confirm_simulation'=>1])->assertRedirect();
  $booking->refresh();$this->assertSame('Paid',$booking->deposit_status);$this->assertSame('Awaiting Hospital Confirmation',$booking->status);$this->assertTrue($booking->payment_is_simulated);$this->assertNotNull($booking->payment_verified_at);$this->assertSame($admin->id,$booking->payment_verified_by);$this->assertNotNull($booking->treatmentRequest->payment_confirmed_at);
  $this->post(route('admin.payments.test-verify',$booking),['confirm_simulation'=>1])->assertConflict();
  $this->get('/admin/payments')->assertOk()->assertSee('SIMULATED PAYMENT')->assertDontSee('Test Verify Payment')->assertViewHas('paymentTotals',fn($s)=>(int)$s['received']===0 && (int)$s['paid']===0 && (int)$s['simulated']===500000);
  $this->get('/admin/bookings')->assertOk()->assertSee('Awaiting Hospital Confirmation')->assertSee('Simulated');
  $this->actingAs($booking->customer)->get('/my-requests')->assertOk()->assertViewHas('requests',fn($r)=>$r->first()['status']==='account_activated' && $r->first()['payment_is_simulated']);
  $this->actingAs($booking->hospital)->get('/hospital/dashboard')->assertOk()->assertSee('Simulated test payment')->assertViewHas('stats',fn($s)=>$s['Confirmed Bookings']===0);
 }
 public function test_permissions_environment_and_confirmation_are_enforced(): void {
  $booking=$this->booking();
  $this->postJson(route('admin.payments.test-verify',$booking),['confirm_simulation'=>1])->assertUnauthorized();
  foreach(['user','hospital'] as $role){$this->actingAs(User::factory()->create(['role'=>$role]))->postJson(route('admin.payments.test-verify',$booking),['confirm_simulation'=>1])->assertForbidden();$this->postJson(route('admin.payments.mark-test',$booking),['confirm_simulation'=>1])->assertForbidden();}
  $this->actingAs(User::factory()->create(['role'=>'admin']))->postJson(route('admin.payments.test-verify',$booking),[])->assertUnprocessable();
  $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
  $this->app->instance('env','production');
  $this->postJson(route('admin.payments.test-verify',$booking),['confirm_simulation'=>1])->assertNotFound();$this->postJson(route('admin.payments.mark-test',$booking),['confirm_simulation'=>1])->assertNotFound();
  $this->assertSame('Pending',$booking->fresh()->deposit_status);
 }
 public function test_existing_records_require_explicit_opt_in_and_paid_history_is_untouched(): void {
  $booking=$this->booking(false);$admin=User::factory()->create(['role'=>'admin']);$this->actingAs($admin);
  $this->postJson(route('admin.payments.test-verify',$booking),['confirm_simulation'=>1])->assertConflict();
  $this->post(route('admin.payments.mark-test',$booking),['confirm_simulation'=>1])->assertRedirect();$this->assertSame($admin->id,$booking->fresh()->test_marked_by);
  $paid=$this->booking(false);$paid->update(['status'=>'Confirmed','deposit_status'=>'Paid','deposit_amount_satang'=>350000]);$before=$paid->fresh()->getRawOriginal();
  $this->postJson(route('admin.payments.mark-test',$paid),['confirm_simulation'=>1])->assertConflict();$this->postJson(route('admin.payments.test-verify',$paid),['confirm_simulation'=>1])->assertConflict();$this->assertSame($before,$paid->fresh()->getRawOriginal());
  $this->get('/admin/payments')->assertViewHas('paymentTotals',fn($s)=>(int)$s['received']===350000 && (int)$s['paid']===1);
 }
 public function test_filters_and_pagination_use_database_rows(): void {
  $booking=$this->booking();$other=$this->booking(false);$this->actingAs(User::factory()->create(['role'=>'admin']));
  $this->get('/admin/payments?search='.urlencode($booking->reference).'&status=Pending%20Payment&size=5')->assertOk()->assertViewHas('realBookings',fn($p)=>$p->total()===1 && $p->first()->id===$booking->id);
  $this->get('/admin/payments?records=real')->assertViewHas('realBookings',fn($p)=>$p->total()===1 && $p->first()->id===$other->id);
  $this->get('/admin/payments?from='.today()->addDay()->format('Y-m-d'))->assertViewHas('realBookings',fn($p)=>$p->total()===0);
 }

 public function test_booking_confirmation_is_authorized_and_independent_of_payment(): void {
  $booking=$this->booking();
  $this->actingAs($booking->customer)->postJson(route('admin.bookings.status',$booking),['status'=>'Confirmed','appointment_date'=>'2027-01-10'])->assertForbidden();
  $this->actingAs(User::factory()->create(['role'=>'admin']));
  $this->postJson(route('admin.bookings.status',$booking),['status'=>'Confirmed','appointment_date'=>'2027-01-10'])->assertUnprocessable();
  $this->post(route('admin.payments.test-verify',$booking),['confirm_simulation'=>1])->assertRedirect();
  $this->assertSame('Awaiting Hospital Confirmation',$booking->fresh()->status);
  $this->postJson(route('admin.bookings.status',$booking),['status'=>'Confirmed'])->assertUnprocessable();
  $this->postJson(route('admin.bookings.status',$booking),['status'=>'Confirmed','appointment_date'=>'2027-01-10'])->assertUnprocessable();
  $booking->update(['deposit_phase'=>'treatment','deposit_percentage'=>10,'deposit_amount_satang'=>100000,'is_test'=>false,'payment_is_simulated'=>false]);
  $booking->paymentSubmissions()->create(['purpose'=>'initial','amount_satang'=>500000,'method'=>'Bank Transfer','status'=>'approved','submitted_at'=>now(),'reviewed_at'=>now(),'reviewer_id'=>auth()->id()]);
  $booking->paymentSubmissions()->create(['purpose'=>'treatment','amount_satang'=>100000,'method'=>'Bank Transfer','status'=>'approved','submitted_at'=>now(),'reviewed_at'=>now(),'reviewer_id'=>auth()->id()]);
  $this->post(route('admin.bookings.status',$booking),['status'=>'Confirmed','appointment_date'=>'2027-01-10'])->assertRedirect();
  $this->assertSame('Paid',$booking->fresh()->deposit_status);
  $this->assertSame('2027-01-10',$booking->fresh()->appointment_date->format('Y-m-d'));
  $this->post(route('admin.bookings.status',$booking),['status'=>'Completed'])->assertRedirect();
  $this->assertSame('Completed',$booking->fresh()->status);
 }
 public function test_missing_amounts_links_dates_and_repeated_reads_do_not_invent_money(): void {
  $booking=$this->booking(false);$booking->update(['deposit_amount_satang'=>null]);
  $this->actingAs(User::factory()->create(['role'=>'admin']));
  $this->get('/admin/bookings?record='.$booking->id)->assertOk()->assertSee('Booking Details')->assertSee('View Payment Details')->assertSee('Not recorded');
  $this->get('/admin/payments?record='.$booking->id)->assertOk()->assertSee('View Booking Details')->assertViewHas('paymentTotals',fn($t)=>(int)$t['received']===0);
  $this->assertNull((new \App\Services\DepositPayment)->summary($booking)['deposit_satang']);
  $this->assertSame('Pending',$booking->fresh()->deposit_status);
  $booking->update(['deposit_amount_satang'=>400000,'deposit_status'=>'Paid','payment_verified_at'=>now()->subDays(3),'status'=>'Awaiting Hospital Confirmation']);
  for($i=0;$i<2;$i++)$this->get('/admin/payments')->assertViewHas('paymentTotals',fn($t)=>(int)$t['received']===400000 && (int)$t['paid']===1);
  $this->get('/admin/payments?from='.today()->format('Y-m-d'))->assertViewHas('realBookings',fn($r)=>$r->total()===0);
  $this->get('/admin/bookings?from='.today()->format('Y-m-d'))->assertViewHas('realBookings',fn($r)=>$r->total()===1);
  $this->get('/admin/payments?search='.sprintf('PAY-%s-%06d',$booking->created_at->format('Y'),$booking->id))->assertViewHas('realBookings',fn($r)=>$r->total()===1);
 }
 public function test_deposit_formula_caps_and_rounding(): void {
  foreach([5000000=>500000,7000000=>500000,8000000=>500000,15000000=>500000,100000=>100000,1000000=>200000,3000000=>300000] as $total=>$deposit)$this->assertSame($deposit,\App\Services\DepositPayment::calculate($total));
 }
}
