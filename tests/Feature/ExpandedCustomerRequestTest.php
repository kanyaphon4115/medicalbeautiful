<?php
namespace Tests\Feature;
use App\Models\{User,TreatmentRequest,RequestPhoto};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Tests\TestCase;
class ExpandedCustomerRequestTest extends TestCase {
 use RefreshDatabase;
 private function payload(): array {
  return ['name'=>'Submitted Full Name','email'=>'submitted@example.test','line_id'=>'my-line-id','phone_number'=>'+66 81 234 5678','age'=>35,'gender'=>'Female','procedures'=>['Eye Surgery','Chin Surgery','Skin Treatments'],'preferred_surgery_month'=>10,'preferred_surgery_year'=>now()->year+1,'goals'=>'Natural facial results','medical_information'=>'Private medical details','consent_to_share'=>true];
 }
 private function photo() {return UploadedFile::fake()->createWithContent('photo.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3ioAAAAASUVORK5CYII='));}
 public function test_request_round_trips_across_customer_hospital_and_admin_views(): void {
  Storage::fake('request_photos');$owner=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);$admin=User::factory()->create(['role'=>'admin']);
  $payload=$this->payload();$payload['photos']=array_map(fn()=>$this->photo(),range(1,10));
  $this->actingAs($owner)->postJson('/requests',$payload)->assertCreated();
  $item=TreatmentRequest::firstOrFail();
  $this->assertSame($payload['procedures'],$item->procedure_names);$this->assertSame('+66812345678',$item->phone_number);$this->assertSame('my-line-id',$item->line_id);
  $this->assertNotSame('my-line-id',DB::table('treatment_requests')->value('line_id'));
  $this->assertNull($item->preferred_surgery_date);$this->assertSame('October '.(now()->year+1),$item->preferred_period_label);
  $this->assertDatabaseCount('treatment_requests',1);$this->assertDatabaseCount('request_photos',10);$photo=RequestPhoto::firstOrFail();
  $this->get('/my-requests')->assertOk()->assertSee('Eye Surgery, Chin Surgery, Skin Treatments')->assertSee('my-line-id')->assertSee('+66812345678');
  $this->get(route('requests.photos.show',$photo))->assertOk();
  $this->actingAs($hospital)->get('/hospital/dashboard')->assertOk()->assertSee('Eye Surgery, Chin Surgery, Skin Treatments')->assertSee('my-line-id')->assertSee($item->preferred_period_label);
  $this->get(route('requests.photos.show',$photo))->assertOk()->assertHeader('Cache-Control','no-store, private');
  $this->actingAs($admin)->get('/admin/requests')->assertOk()->assertSee('Submitted Full Name')->assertSee('Private medical details')->assertSee('my-line-id');
  $this->get(route('requests.photos.show',$photo))->assertOk();
  $this->actingAs(User::factory()->create())->get(route('requests.photos.show',$photo))->assertNotFound();$this->get('/my-requests')->assertDontSee('my-line-id');
  $other=User::factory()->create(['role'=>'hospital']);$item->update(['selected_hospital_id'=>$other->id]);
  $this->actingAs($hospital)->get(route('requests.photos.show',$photo))->assertNotFound();$this->get('/hospital/dashboard')->assertDontSee('my-line-id');
 }
 public function test_invalid_procedures_contacts_and_periods_are_rejected(): void {
  $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
  $this->actingAs(User::factory()->create());
  foreach ([['procedures'=>[]],['procedures'=>['Fake Procedure']],['procedures'=>['Eye Surgery','Eye Surgery']],['age'=>17],['age'=>121],['age'=>22.5],['gender'=>'Invalid'],['phone_number'=>'0812345678'],['preferred_surgery_month'=>13],['preferred_surgery_year'=>now()->year-1],['preferred_surgery_year'=>null]] as $invalid) $this->postJson('/requests',array_replace($this->payload(),$invalid))->assertUnprocessable();
  $this->assertDatabaseCount('treatment_requests',0);
 }
 public function test_legacy_requests_keep_their_procedure_and_preferred_month(): void {
  $owner=User::factory()->create();$this->actingAs($owner)->postJson('/requests',['name'=>'Legacy Customer','email'=>$owner->email,'procedure'=>'Rhinoplasty','preferred_surgery_date'=>today()->addMonth()->format('Y-m-d'),'consent_to_share'=>true])->assertCreated();
  $item=TreatmentRequest::firstOrFail();$item->update(['procedures'=>null]);$this->assertSame(['Rhinoplasty'],$item->fresh()->procedure_names);$this->assertSame(today()->addMonth()->format('F Y'),$item->fresh()->preferred_period_label);$this->get('/my-requests')->assertOk()->assertSee('Rhinoplasty');
 }
}
