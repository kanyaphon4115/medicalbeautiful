<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\TreatmentRequest;
use App\Models\RequestPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class TreatmentRequestsTest extends TestCase {
 use RefreshDatabase;
 public function test_new_facial_categories_can_be_submitted_from_homepage(): void {
  $this->actingAs(User::factory()->create());
  foreach(['Chin Surgery','Facial Fat Reduction','Skin Treatments'] as $procedure){
   $this->get('/')->assertOk()->assertSee('data-procedure="'.$procedure.'"',false);
   $this->postJson('/requests',array_replace($this->payload(),['procedure'=>$procedure]))->assertCreated();
   $this->assertDatabaseHas('treatment_requests',['procedure'=>$procedure]);
  }
 }
 private function photo() { return UploadedFile::fake()->createWithContent('photo.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3ioAAAAASUVORK5CYII=')); }
 private function payload(): array { return ['name'=>'Test Customer','email'=>'customer@example.com','procedure'=>'Eye Surgery','goals'=>'A natural look','medical_information'=>'Sample allergy information','preferred_surgery_date'=>now()->addMonth()->toDateString(),'consent_to_share'=>'1']; }
 public function test_guests_cannot_submit_or_view_private_requests(): void {
  $this->get('/my-requests')->assertRedirect(route('login'));
  $this->postJson('/requests',$this->payload())->assertUnauthorized();
  $this->assertDatabaseCount('treatment_requests',0);
 }
 public function test_requests_and_photos_are_saved_privately_and_only_owner_can_access(): void {
  Storage::fake('request_photos');$owner=User::factory()->create();$other=User::factory()->create();
  $payload=$this->payload();$payload['photos']=[$this->photo()];$payload['user_id']=$other->id;
  $this->actingAs($owner)->postJson('/requests',$payload)->assertCreated()->assertJsonPath('redirect',route('requests.index'));
  $request=TreatmentRequest::firstOrFail();$photo=RequestPhoto::firstOrFail();
  $this->assertSame($owner->id,$request->user_id);$this->assertSame('pending',$request->status);
  $this->assertSame('Sample allergy information',$request->medical_information);
  $this->assertNotSame('Sample allergy information',DB::table('treatment_requests')->value('medical_information'));
  $this->assertNotSame('A natural look',DB::table('treatment_requests')->value('goals'));
  $this->assertNotNull($request->consented_at);Storage::disk('request_photos')->assertExists($photo->path);
  $this->get('/my-requests')->assertOk()->assertSee('customer@example.com')->assertSee('Sample allergy information');
  $this->get(route('requests.photos.show',$photo))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
  $this->actingAs($other)->get('/my-requests')->assertOk()->assertDontSee('Sample allergy information')->assertDontSee('customer@example.com');
  $this->get(route('requests.photos.show',$photo))->assertNotFound();
 }
 public function test_invalid_requests_cannot_save_records_or_files(): void {
  Storage::fake('request_photos');$this->actingAs(User::factory()->create());
  $payload=$this->payload();unset($payload['consent_to_share']);$payload['preferred_surgery_date']=now()->subDay()->toDateString();$payload['photos']=[UploadedFile::fake()->create('document.pdf',100,'application/pdf')];
  $this->postJson('/requests',$payload)->assertUnprocessable()->assertJsonValidationErrors(['consent_to_share','preferred_surgery_date','photos.0']);
  $this->assertDatabaseCount('treatment_requests',0);$this->assertSame([],Storage::disk('request_photos')->allFiles());
 }
 public function test_photo_count_and_size_limits_are_enforced(): void {
  Storage::fake('request_photos');$this->actingAs(User::factory()->create());
  $payload=$this->payload();$payload['photos']=array_map(fn()=>$this->photo(),range(1,11));
  $this->postJson('/requests',$payload)->assertUnprocessable()->assertJsonValidationErrors('photos');
  $payload['photos']=[$this->photo()->size(5121)];
  $this->postJson('/requests',$payload)->assertUnprocessable()->assertJsonValidationErrors('photos.0');
  $this->assertDatabaseCount('treatment_requests',0);
 }
 public function test_optional_fields_can_be_omitted(): void {
  $this->actingAs(User::factory()->create())->postJson('/requests',['name'=>'Test','email'=>'test@example.com','procedure'=>'Rhinoplasty','consent_to_share'=>true])->assertCreated();
  $this->assertDatabaseCount('treatment_requests',1);$this->assertDatabaseCount('request_photos',0);
 }
}
