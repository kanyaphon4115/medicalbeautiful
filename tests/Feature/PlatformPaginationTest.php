<?php
namespace Tests\Feature;
use App\Models\{User,TreatmentRequest,Quotation,HospitalProfile,HospitalVerificationChange,PatientReview};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;
class PlatformPaginationTest extends TestCase {
 use RefreshDatabase; use \Tests\Concerns\HasApprovedChatBooking;
 private function records($owner,$count=16){return collect(range(1,$count))->map(fn($n)=>TreatmentRequest::create(['reference'=>fake()->uuid(),'user_id'=>$owner->id,'name'=>$owner->name,'email'=>$owner->email,'procedure'=>'Eye Surgery','status'=>'pending','consented_at'=>now(),'consent_text'=>'Consent','created_at'=>now()->subDays(20-$n)]));}
 public function test_numbered_window_reaches_every_page_and_hides_for_small_lists(){
  foreach([0,1,5,6,11,26] as $total){$last=max(1,(int)ceil($total/5));foreach(range(1,$last) as $page){$p=new LengthAwarePaginator([], $total,5,$page,['path'=>'/records','query'=>['search'=>'retain']]);$html=$p->links()->toHtml();if($total<=5){$this->assertSame('',trim($html));continue;}$this->assertStringNotContainsString('Previous',$html);$this->assertStringNotContainsString('Next',$html);$this->assertSame(min(3,$last),substr_count($html,'<a '));$this->assertStringContainsString('aria-current="page"',$html);$this->assertStringContainsString('search=retain',$html);$this->assertStringContainsString('page='.$page.'"',$html);}}
 }
 public function test_customer_requests_paginate_database_and_keep_filter_sort_and_global_counts(){
  $user=User::factory()->create();$rows=$this->records($user);$rows[0]->update(['status'=>'completed']);$this->records(User::factory()->create(),6);
  $r=$this->actingAs($user)->get('/my-requests?page=2')->assertOk();$this->assertCount(5,$r->viewData('requests'));$this->assertSame(16,$r->viewData('requestPage')->total());$this->assertSame(16,$r->viewData('filterCounts')['all']);
  $r=$this->get('/my-requests?filter=in_progress&sort=oldest&page=3')->assertOk();$this->assertCount(5,$r->viewData('requests'));$this->assertSame(15,$r->viewData('requestPage')->total());
  $this->get('/my-requests?filter=completed')->assertOk()->assertViewHas('requests',fn($r)=>$r->count()===1);
 }
 public function test_hospital_requests_and_quotations_are_separately_paginated_with_full_statistics(){
  $hospital=User::factory()->create(['role'=>'hospital']);$rows=$this->records(User::factory()->create());foreach($rows as $item)Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>5000,'details'=>'Offer','valid_until'=>today()->addDays(3)]);
  $r=$this->actingAs($hospital)->get('/hospital/dashboard?requests_page=2&quotations_page=3')->assertOk();$this->assertCount(5,$r->viewData('requests'));$this->assertCount(5,$r->viewData('quotations'));$this->assertSame(16,$r->viewData('stats')['Quotations Sent']);$this->assertSame(16,$r->viewData('requestTotal'));
 }
 public function test_support_customer_and_request_lists_ignore_requested_larger_page_sizes(){
  $this->records(User::factory()->create());User::factory()->count(15)->create();$this->actingAs(User::factory()->create(['role'=>'admin']));
  $this->get('/admin/customers?size=20&page=2')->assertOk()->assertViewHas('customers',fn($p)=>$p->count()===5&&$p->perPage()===5);
  $this->get('/admin/requests?page=2')->assertOk()->assertViewHas('realRequests',fn($p)=>$p->count()===5&&$p->perPage()===5);
 }
 public function test_review_moderation_and_verification_lists_use_five_records(){
  $user=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);$profile=HospitalProfile::create(['hospital_id'=>$hospital->id]);
  for($i=0;$i<12;$i++){PatientReview::create(['user_id'=>$user->id,'rating'=>5,'title'=>'Patient Experience','body'=>'Actual review','procedure'=>'Eye Surgery','status'=>'published','published_at'=>now(),'anonymous'=>true]);HospitalVerificationChange::create(['hospital_profile_id'=>$profile->id,'payload'=>['registered_name'=>'Private','license_number'=>null,'documents'=>[]],'status'=>'pending']);}
  $this->get('/patient-reviews?page=2')->assertOk()->assertViewHas('reviews',fn($p)=>$p->count()===5&&$p->total()===12);
  $this->actingAs(User::factory()->create(['role'=>'admin']));$this->get(route('reviews.moderation',['page'=>2]))->assertOk()->assertViewHas('reviews',fn($p)=>$p->count()===5);
  $this->get(route('hospital.verifications',['page'=>2]))->assertOk()->assertViewHas('changes',fn($p)=>$p->count()===5);
 }
 public function test_booking_payment_and_transaction_pages_always_limit_database_results_to_five(){
  for($i=0;$i<12;$i++)$this->approvedChatBooking(User::factory()->create());
  $this->actingAs(User::factory()->create(['role'=>'admin']));
  $this->get('/admin/bookings?size=25&page=2')->assertOk()->assertViewHas('realBookings',fn($p)=>$p->count()===5&&$p->total()===12);
  $this->get('/admin/payments?size=25&page=2')->assertOk()->assertViewHas('paymentTransactions',fn($p)=>$p->count()===5&&$p->total()===24);
 }
 public function test_hospital_booking_controls_reach_later_pages_and_restore_the_booking_tab(){
  $hospital=null;
  for($i=0;$i<12;$i++){
   $booking=$this->approvedChatBooking(User::factory()->create());
   $hospital??=$booking->hospital;
   $booking->update(['hospital_id'=>$hospital->id]);
  }
  $response=$this->actingAs($hospital)->get('/hospital/dashboard?section=Bookings&bookings_page=2')->assertOk();
  $response->assertViewHas('bookings',fn($rows)=>$rows->count()===5&&$rows->total()===12&&$rows->currentPage()===2);
  $html=$response->getContent();
  preg_match('/<template id="hd-bookings">(.*?)<\/template>/s',$html,$matches);
  $this->assertStringContainsString('class="ae-pagination"',$matches[1]);
  $this->assertStringContainsString('bookings_page=3',$matches[1]);
  $this->assertStringContainsString('section=Bookings',$matches[1]);
  $this->assertStringNotContainsString('Previous',$matches[1]);
  $this->assertStringNotContainsString('Next',$matches[1]);
 }
}


