<?php
namespace Tests\Feature;
use App\Models\{PatientReview,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientReviewsTest extends TestCase {
 use RefreshDatabase; use \Tests\Concerns\HasApprovedChatBooking;
 private function photo(string $name):UploadedFile {return UploadedFile::fake()->createWithContent($name,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));}
 private function review(array $attributes=[]):PatientReview {
  $customer=User::factory()->create(['name'=>'Private Patient Name']);$booking=$this->approvedChatBooking($customer);
  $booking->update(['status'=>'Completed']);
  return PatientReview::create(['user_id'=>$customer->id,'booking_id'=>$booking->id,'procedure'=>'Rhinoplasty','rating'=>5,'title'=>'Natural result','body'=>'My recovery was smooth and the result feels natural.','status'=>'published','published_at'=>now(),...$attributes]);
 }
 public function test_public_page_has_real_statistics_filters_and_masks_private_information():void {
  $review=$this->review();$review->update(['body'=>'Private Patient Name had treatment at '.$review->booking->hospital->name.'. Contact private@example.test or +66 812345678.']);
  $this->review(['rating'=>3,'procedure'=>'Eye Surgery','title'=>'A good recovery']);
  $this->review(['status'=>'pending','title'=>'NOT-PUBLIC']);
  $this->get(route('reviews.index'))->assertOk()->assertSee('Patient Reviews')->assertSee('Natural result')->assertSee('P***')->assertDontSee('Private Patient Name')->assertDontSee('private@example.test')->assertDontSee('NOT-PUBLIC')->assertDontSee($review->booking->hospital->name)->assertViewHas('stats',fn($s)=>$s['count']===2&&(float)$s['average']===4.0&&$s['photos']===0);
  $this->get(route('reviews.index',['q'=>'Rhinoplasty']))->assertSee('Natural result')->assertDontSee('A good recovery');
  $this->get(route('reviews.index',['procedure'=>'Eye Surgery','rating'=>3]))->assertSee('A good recovery')->assertDontSee('Natural result');
  $this->get(route('reviews.index',['photos'=>1]))->assertDontSee('Natural result');
  $this->get(route('reviews.show',$review))->assertRedirect(route('reviews.index').'#review-'.$review->id);
 }
 public function test_unpublished_or_unconsented_photos_are_never_public():void {
  Storage::fake('local');Storage::disk('local')->put('patient-reviews/before.jpg','private-photo');
  $review=$this->review(['photo_consent'=>true,'before_path'=>'patient-reviews/before.jpg','after_path'=>'patient-reviews/after.jpg']);
  $this->get(route('reviews.photo',[$review,'before']))->assertOk();
  $review->update(['photo_consent'=>false]);$this->get(route('reviews.photo',[$review,'before']))->assertNotFound();
  $review->update(['photo_consent'=>true,'status'=>'pending']);
  $this->get(route('reviews.photo',[$review,'before']))->assertNotFound();$this->get(route('reviews.show',$review))->assertNotFound();
  $review->update(['status'=>'published','published_at'=>now()->addDay()]);$this->get(route('reviews.show',$review))->assertNotFound();
 }
 public function test_only_verified_customers_can_post_and_cannot_use_another_customers_booking():void {
  $customer=User::factory()->create();$booking=$this->approvedChatBooking($customer);$booking->update(['status'=>'Completed']);
  $data=['booking_id'=>$booking->id,'rating'=>5,'title'=>'My patient experience','body'=>'Recovery went well and I am very happy with the natural result.','privacy_consent'=>1,'status'=>'published','user_id'=>999];
  $this->post(route('reviews.store'),$data)->assertRedirect(route('login'));
  $outsider=User::factory()->create();$this->actingAs($outsider)->post(route('reviews.store'),$data)->assertForbidden();
  $this->actingAs($customer)->get(route('reviews.create'))->assertOk()->assertDontSee('Select Your Completed Treatment')->assertDontSee('Review Title');
  $booking->update(['status'=>'Confirmed']);
  $customer->forceFill(['email_verified_at'=>null])->save();$this->post(route('reviews.store'),$data)->assertForbidden();
  $customer->forceFill(['email_verified_at'=>now()])->save();$this->post(route('reviews.store'),$data)->assertRedirect();
  $this->assertDatabaseHas('patient_reviews',['user_id'=>$customer->id,'booking_id'=>$booking->id,'status'=>'published']);
  $this->post(route('reviews.store'),$data)->assertConflict();
  $this->actingAs(User::factory()->create(['role'=>'hospital']))->get(route('reviews.create'))->assertForbidden();
 }
 public function test_photo_uploads_require_a_pair_and_consent_and_strip_metadata():void {
  Storage::fake('local');$customer=User::factory()->create();$booking=$this->approvedChatBooking($customer);$booking->update(['status'=>'Completed']);$this->actingAs($customer);
  $data=['booking_id'=>$booking->id,'rating'=>4,'title'=>'A natural result','body'=>'My treatment and recovery were a positive experience.','privacy_consent'=>1];
  $this->postJson(route('reviews.store'),[...$data,'before'=>$this->photo('before.png')])->assertUnprocessable();
  $this->postJson(route('reviews.store'),[...$data,'before'=>$this->photo('before.png'),'after'=>$this->photo('after.png')])->assertUnprocessable();
  $this->post(route('reviews.store'),[...$data,'photo_consent'=>1,'before'=>$this->photo('before.png'),'after'=>$this->photo('after.png')])->assertRedirect();
  $review=PatientReview::firstOrFail();$this->assertTrue($review->has_photos);Storage::disk('local')->assertExists($review->before_path);
  $this->get(route('reviews.photo',[$review,'before']))->assertOk();
 }
 public function test_pagination_and_empty_state_do_not_invent_patient_results():void {
  $this->get(route('reviews.index'))->assertOk()->assertSee('Patient stories start here.')->assertViewHas('stats',fn($s)=>$s['count']===0&&$s['average']===null);
  for($i=0;$i<9;$i++)$this->review();
  $this->get(route('reviews.index'))->assertViewHas('reviews',fn($r)=>$r->count()===5&&$r->total()===9);
  $this->get(route('reviews.index',['page'=>2]))->assertViewHas('reviews',fn($r)=>$r->count()===4);
 }
 public function test_customer_support_can_moderate_but_customers_and_hospitals_cannot():void {
  Storage::fake('local');Storage::disk('local')->put('patient-reviews/moderation.png','private-photo');
  $review=$this->review(['status'=>'pending','published_at'=>null,'anonymous'=>true,'photo_consent'=>false,'before_path'=>'patient-reviews/moderation.png','after_path'=>'patient-reviews/moderation.png']);
  foreach([$review->customer,User::factory()->create(['role'=>'hospital'])] as $actor){
   $this->actingAs($actor)->get(route('reviews.moderation'))->assertForbidden();
   $this->post(route('reviews.moderate',$review),['decision'=>'published'])->assertForbidden();
   $this->get(route('reviews.moderation-photo',[$review,'before']))->assertForbidden();
  }
  $support=User::factory()->create(['role'=>'support']);$this->actingAs($support)->get(route('reviews.moderation'))->assertOk()->assertSee('Natural result');
  $this->get(route('reviews.moderation-photo',[$review,'before']))->assertOk();
  $this->post(route('reviews.moderate',$review),['decision'=>'published'])->assertRedirect();
  $this->assertDatabaseHas('patient_reviews',['id'=>$review->id,'status'=>'published','reviewer_id'=>$support->id]);
  $this->get(route('reviews.index'))->assertSee('Anonymous patient')->assertDontSee('Private Patient Name');
  $this->get(route('reviews.photo',[$review,'before']))->assertNotFound();
  $this->post(route('reviews.moderate',$review),['decision'=>'rejected'])->assertRedirect();
  $this->get(route('reviews.show',$review))->assertNotFound();
 }
 public function test_modal_validation_anonymous_preference_and_json_submission():void {
  $customer=User::factory()->create();$booking=$this->approvedChatBooking($customer);$booking->update(['status'=>'Completed']);$this->actingAs($customer);
  $this->get(route('reviews.index'))->assertOk()->assertSee('data-review-dialog',false)->assertDontSee('Post anonymously')->assertSee('+ Add Photos');
  $data=['booking_id'=>$booking->id,'rating'=>5,'title'=>'My treatment review','body'=>str_repeat('a',49),'privacy_consent'=>1,'anonymous'=>1];
  $this->postJson(route('reviews.store'),[...$data,'body'=>str_repeat('a',5001)])->assertUnprocessable()->assertJsonValidationErrors('body');
  $data['body']='My treatment experience and recovery were smooth and comfortable.';
  $this->postJson(route('reviews.store'),[...$data,'rating'=>6])->assertUnprocessable()->assertJsonValidationErrors('rating');
  $this->postJson(route('reviews.store'),[...$data,'before'=>UploadedFile::fake()->create('large.png',10241,'image/png')])->assertUnprocessable()->assertJsonValidationErrors('before');
  $this->postJson(route('reviews.store'),$data)->assertCreated()->assertJsonStructure(['message']);
  $this->assertDatabaseHas('patient_reviews',['booking_id'=>$booking->id,'anonymous'=>true,'status'=>'published']);
  $this->get(route('reviews.index'))->assertSee('My treatment review');
 }
 public function test_png_and_jpeg_metadata_are_removed_without_gd():void {
  $source=$this->photo('before.png');$png=file_get_contents($source->getRealPath());
  $private='Location'."\0".'PRIVATE GPS';$chunk=pack('N',strlen($private)).'tEXt'.$private.pack('N',crc32('tEXt'.$private));
  $png=substr($png,0,-12).$chunk.substr($png,-12);
  $result=app(\App\Services\ReviewPhoto::class)->clean(UploadedFile::fake()->createWithContent('private.png',$png));
  $this->assertStringNotContainsString('PRIVATE GPS',$result['bytes']);$this->assertSame('png',$result['extension']);
  $jpg=file_get_contents(public_path('images/eye.jpg'));$metadata='Exif'."\0\0".'PRIVATE GPS';
  $jpg=substr($jpg,0,2)."\xff\xe1".pack('n',strlen($metadata)+2).$metadata.substr($jpg,2);
  $result=app(\App\Services\ReviewPhoto::class)->clean(UploadedFile::fake()->createWithContent('private.jpg',$jpg));
  $this->assertStringNotContainsString('PRIVATE GPS',$result['bytes']);$this->assertSame('jpg',$result['extension']);
  $this->assertNotFalse(getimagesizefromstring($result['bytes']));
 }
 public function test_general_photo_gallery_accepts_many_photos_without_title_or_treatment_selection():void {
  Storage::fake('local');$customer=User::factory()->create();$booking=$this->approvedChatBooking($customer);$booking->update(['status'=>'Completed']);$this->actingAs($customer);
  $ids=[];for($i=0;$i<25;$i++)$ids[]=$this->postJson(route('reviews.upload'),['photo'=>$this->photo('photo.png')])->assertCreated()->json('id');
  $data=['rating'=>5,'body'=>'I am very happy with my treatment experience and the natural results.','privacy_consent'=>1,'photo_ids'=>$ids];
  $this->postJson(route('reviews.store'),$data)->assertUnprocessable()->assertJsonValidationErrors('photo_consent');
  $this->postJson(route('reviews.store'),[...$data,'photo_consent'=>1])->assertCreated();
  $review=PatientReview::firstOrFail();$this->assertSame('',$review->title);$this->assertNull($review->booking_id);$this->assertCount(25,$review->photos);
  $photo=$review->photos->first();$url=route('reviews.gallery-photo',[$review,$photo]);$this->get($url)->assertOk();
  $this->actingAs(User::factory()->create(['role'=>'support']))->post(route('reviews.moderate',$review),['decision'=>'published'])->assertRedirect();
  $this->get($url)->assertOk();$this->get(route('reviews.show',$review))->assertRedirect(route('reviews.index').'#review-'.$review->id);
  $review->update(['photo_consent'=>false]);$this->get($url)->assertNotFound();
 }
 public function test_uploaded_photos_cannot_be_stolen_or_reused_for_another_review():void {
  Storage::fake('local');$owner=User::factory()->create();$booking=$this->approvedChatBooking($owner);$booking->update(['status'=>'Completed']);
  $id=$this->actingAs($owner)->postJson(route('reviews.upload'),['photo'=>$this->photo('photo.png')])->assertCreated()->json('id');
  $other=User::factory()->create();$otherBooking=$this->approvedChatBooking($other);$otherBooking->update(['status'=>'Completed']);
  $data=['rating'=>5,'body'=>'The treatment experience was comfortable and the results look natural.','privacy_consent'=>1,'photo_consent'=>1,'photo_ids'=>[$id]];
  $this->actingAs($other)->postJson(route('reviews.store'),$data)->assertUnprocessable();
  $this->actingAs($owner)->postJson(route('reviews.store'),$data)->assertCreated();
  $review=PatientReview::firstOrFail();$newPhoto=\App\Models\PatientReviewPhoto::create(['user_id'=>$other->id,'path'=>'private.png']);
  $review->update(['status'=>'published','published_at'=>now()]);$this->get(route('reviews.gallery-photo',[$review,$newPhoto]))->assertNotFound();
  $this->actingAs($other)->postJson(route('reviews.store'),$data)->assertUnprocessable();
 }
 public function test_review_text_is_optional_and_accepts_short_text():void {
  foreach([null,'','ดี'] as $body){
   $customer=User::factory()->create();$booking=$this->approvedChatBooking($customer);$booking->update(['status'=>'Completed']);
   $data=['rating'=>5,'privacy_consent'=>1];if($body!==null)$data['body']=$body;
   $this->actingAs($customer)->postJson(route('reviews.store'),$data)->assertCreated();
   $this->assertDatabaseHas('patient_reviews',['user_id'=>$customer->id,'body'=>$body??'','status'=>'published']);
  }
 }
 public function test_customer_without_any_booking_can_post_photos_and_guests_can_see_them_immediately():void {
  Storage::fake('local');$customer=User::factory()->create();$this->actingAs($customer);
  $this->get(route('reviews.index'))->assertOk()->assertDontSee('You can write a review after completing')->assertSee('<button type="submit" class="pr-modal-submit">',false);
  $photoId=$this->postJson(route('reviews.upload'),['photo'=>$this->photo('photo.png')])->assertCreated()->json('id');
  $data=['rating'=>5,'body'=>'is good','privacy_consent'=>1,'photo_consent'=>1,'photo_ids'=>[$photoId],'submission_key'=>(string)\Illuminate\Support\Str::uuid()];
  $response=$this->postJson(route('reviews.store'),$data)->assertCreated()->assertJsonPath('status','published');
  $this->postJson(route('reviews.store'),$data)->assertCreated();$this->assertDatabaseCount('patient_reviews',1);
  $review=PatientReview::firstOrFail();$this->assertNull($review->booking_id);
  $this->assertSame(route('reviews.index').'#review-'.$review->id,$response->json('url'));
  \Illuminate\Support\Facades\Auth::logout();
  $this->get(route('reviews.index'))->assertOk()->assertSee('is good');
  $this->get($response->json('url'))->assertOk()->assertSee('is good');
  $this->get(route('reviews.gallery-photo',[$review,$review->photos->first()]))->assertOk();
 }
}
