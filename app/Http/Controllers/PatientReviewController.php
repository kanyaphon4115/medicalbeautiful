<?php
namespace App\Http\Controllers;
use App\Models\{Booking,PatientReview,PatientReviewPhoto,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage,Gate};

class PatientReviewController extends Controller {
 public function index(Request $request){
  $filters=$request->validate(['q'=>'nullable|string|max:200','procedure'=>'nullable|string|max:120','rating'=>'nullable|integer|between:1,5','photos'=>'nullable|in:1']);
  $base=PatientReview::published();
  $withPhotos=fn($q)=>$q->whereHas('photos')->orWhere(fn($p)=>$p->whereNotNull('before_path')->whereNotNull('after_path'));
  $stats=['count'=>(clone $base)->count(),'average'=>(clone $base)->avg('rating'),'photos'=>(clone $base)->where('photo_consent',true)->where($withPhotos)->count()];
  $procedures=(clone $base)->distinct()->orderBy('procedure')->pluck('procedure');
  $query=(clone $base)->with(['customer','booking.hospital','photos']);
  if($request->filled('q')){$term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$filters['q']).'%';$query->where(fn($q)=>$q->where('title','like',$term)->orWhere('body','like',$term)->orWhere('procedure','like',$term));}
  if($request->filled('procedure'))$query->where('procedure',$filters['procedure']);
  if($request->filled('rating'))$query->where('rating',$filters['rating']);
  if($request->boolean('photos'))$query->where('photo_consent',true)->where($withPhotos);
  $reviewPatient=$request->user()?->role===User::ROLE_USER && $request->user()?->email_verified_at;
  return view('reviews.index',['reviews'=>$query->latest('published_at')->latest('id')->paginate(5)->withQueryString(),'stats'=>$stats,'procedures'=>$procedures,'filters'=>$filters,
   'reviewPatient'=>$reviewPatient]);
 }
 public function show(PatientReview $review){
  abort_unless(PatientReview::published()->whereKey($review->id)->exists(),404);
  $before=PatientReview::published()->where(fn($q)=>$q->where('published_at','>',$review->published_at)->orWhere(fn($same)=>$same->where('published_at',$review->published_at)->where('id','>',$review->id)))->count();
  $page=intdiv($before,5)+1;
  return redirect()->to(route('reviews.index',$page>1?['page'=>$page]:[]).'#review-'.$review->id);
 }
 public function photo(PatientReview $review,string $side){
  abort_unless(in_array($side,['before','after'],true)&&$review->has_photos&&PatientReview::published()->whereKey($review->id)->exists(),404);
  $path=$review->{$side.'_path'};abort_unless(Storage::disk('local')->exists($path),404);
  return response()->file(Storage::disk('local')->path($path),['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
 }
 public function create(Request $request){
  abort_unless($request->user()->role===User::ROLE_USER && $request->user()->email_verified_at,403,'A verified patient account is required.');
  return view('reviews.create',['reviewPatient'=>true]);
 }
 public function store(Request $request){
  abort_unless($request->user()->role===User::ROLE_USER && $request->user()->email_verified_at,403);
  $data=$request->validate(['booking_id'=>'sometimes|nullable|integer','submission_key'=>'sometimes|nullable|uuid','rating'=>'required|integer|between:1,5','title'=>'sometimes|nullable|string|max:150','body'=>'nullable|string|max:5000',
   'privacy_consent'=>'required|accepted','photo_consent'=>'sometimes|accepted','anonymous'=>'sometimes|boolean',
   'photo_ids'=>'sometimes|array','photo_ids.*'=>'required|integer|distinct',
   'before'=>'nullable|required_with:after|image|mimes:jpg,jpeg,png|max:10240|dimensions:max_width=8192,max_height=8192',
   'after'=>'nullable|required_with:before|image|mimes:jpg,jpeg,png|max:10240|dimensions:max_width=8192,max_height=8192']);
  if($request->hasFile('before')||!empty($data['photo_ids']))$request->validate(['photo_consent'=>'required|accepted']);
  $paths=[];
  try{$review=DB::transaction(function()use($request,$data,&$paths){
   User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
   if(!empty($data['submission_key'])){
    $existing=PatientReview::where('user_id',$request->user()->id)->where('submission_key',$data['submission_key'])->first();if($existing)return $existing;
   }
   $booking=null;
   if(isset($data['booking_id'])){
    $booking=Booking::where('user_id',$request->user()->id)->whereKey($data['booking_id'])->lockForUpdate()->first();
    abort_unless($booking,403,'You can only review your own booking.');
    abort_if(PatientReview::where('booking_id',$booking->id)->exists(),409,'This booking already has a review.');
   }
   $photos=PatientReviewPhoto::whereIn('id',$data['photo_ids']??[])->where('user_id',$request->user()->id)->whereNull('patient_review_id')->lockForUpdate()->get();
   abort_unless($photos->count()===count($data['photo_ids']??[]),422,'Some photos are unavailable or belong to another review.');
   foreach(['before','after'] as $side)if($request->hasFile($side)){
    $photo=app(\App\Services\ReviewPhoto::class)->clean($request->file($side));
    $path='patient-reviews/'.\Illuminate\Support\Str::uuid().'.'.$photo['extension'];Storage::disk('local')->put($path,$photo['bytes']);$paths[$side.'_path']=$path;
   }
   $review=PatientReview::create(['user_id'=>$request->user()->id,'booking_id'=>$booking?->id,'procedure'=>$booking?->treatmentRequest?->procedure_label??'Patient Experience',
    'submission_key'=>$data['submission_key']??null,'rating'=>$data['rating'],'title'=>$data['title']??'','body'=>$data['body']??'','anonymous'=>$request->boolean('anonymous'),'photo_consent'=>$request->boolean('photo_consent'),'status'=>'published','published_at'=>now(),...$paths]);
   PatientReviewPhoto::whereIn('id',$photos->pluck('id'))->update(['patient_review_id'=>$review->id]);
   return $review;
  });}catch(\Throwable $e){foreach($paths as $path)Storage::disk('local')->delete($path);throw $e;}
  $url=route('reviews.index').'#review-'.$review->id;
  if($request->expectsJson())return response()->json(['message'=>'Your review has been posted. Other visitors can now see it.','url'=>$url,'status'=>$review->status],201);
  return redirect()->to($url)->with('status','Your review has been posted.');
 }
 public function moderation(){Gate::authorize('viewAny',PatientReview::class);return view('reviews.moderation',['reviews'=>PatientReview::with('customer')->latest()->paginate(5)]);}
 public function moderate(Request $request,PatientReview $review){
  Gate::authorize('moderate',$review);$data=$request->validate(['decision'=>'required|in:published,rejected','note'=>'nullable|string|max:2000']);
  DB::transaction(function()use($request,$review,$data){$row=PatientReview::whereKey($review->id)->lockForUpdate()->firstOrFail();
   $row->update(['status'=>$data['decision'],'published_at'=>$data['decision']==='published'?now():null,'reviewer_id'=>$request->user()->id,'reviewed_at'=>now(),'moderation_note'=>$data['note']??null]);
  });return back()->with('status','Review '.($data['decision']==='published'?'approved.':'rejected.'));
 }
 public function moderationPhoto(PatientReview $review,string $side){
  Gate::authorize('moderate',$review);abort_unless(in_array($side,['before','after'],true),404);
  $path=$review->{$side.'_path'};abort_unless($path&&Storage::disk('local')->exists($path),404);
  return response()->file(Storage::disk('local')->path($path),['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
 }
 public function upload(Request $request){
  abort_unless($request->user()->role===User::ROLE_USER && $request->user()->email_verified_at,403,'Please sign in with a verified patient account to submit your review.');
  $request->validate(['photo'=>'required|image|mimes:jpg,jpeg,png|max:10240|dimensions:max_width=8192,max_height=8192']);
  $clean=app(\App\Services\ReviewPhoto::class)->clean($request->file('photo'));$path='patient-reviews/'.\Illuminate\Support\Str::uuid().'.'.$clean['extension'];
  Storage::disk('local')->put($path,$clean['bytes']);
  try{$photo=PatientReviewPhoto::create(['user_id'=>$request->user()->id,'path'=>$path]);}catch(\Throwable $e){Storage::disk('local')->delete($path);throw $e;}
  return response()->json(['id'=>$photo->id],201);
 }
 public function galleryPhoto(PatientReview $review,PatientReviewPhoto $photo){
  abort_unless($photo->patient_review_id===$review->id && $review->photo_consent && PatientReview::published()->whereKey($review->id)->exists(),404);
  return $this->photoFile($photo);
 }
 public function moderationGalleryPhoto(PatientReview $review,PatientReviewPhoto $photo){Gate::authorize('moderate',$review);abort_unless($photo->patient_review_id===$review->id,404);return $this->photoFile($photo);}
 private function photoFile(PatientReviewPhoto $photo){abort_unless(Storage::disk('local')->exists($photo->path),404);return response()->file(Storage::disk('local')->path($photo->path),['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);}
}
