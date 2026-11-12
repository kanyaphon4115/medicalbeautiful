<?php
namespace App\Http\Controllers;
use App\Models\TreatmentRequest;
use App\Models\RequestPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;
class TreatmentRequestController extends Controller {
 public const PROCEDURES = ['Eye Surgery','Rhinoplasty','Lip Surgery','Facial Contouring','Face Lifting','Liposuction','Breast Surgery','Other Procedures','Chin Surgery','Facial Fat Reduction','Skin Treatments'];
 public const CONSENT = 'I consent to sharing my personal information, photos, and medical information with partner hospitals to receive treatment consultations and quotations.';
 public function profile(Request $request) {
  $user=$request->user();
  $previous=TreatmentRequest::where('user_id',$user->id)->latest('id')->first();
  return response()->json(['profile'=>[
   'name'=>$user->name,'email'=>$user->email,
   'phone_number'=>$user->phone_number ?? $previous?->phone_number ?? '',
   'line_id'=>$user->line_id ?? $previous?->line_id ?? '',
   'date_of_birth'=>$user->date_of_birth ?? $previous?->date_of_birth ?? '',
   'gender'=>$user->gender ?? $previous?->gender ?? '',
  ]])->header('Cache-Control','private, no-store');
 }
 public function index(Request $request) {
  $images = ['Eye Surgery'=>'eye.jpg','Rhinoplasty'=>'nose.jpg','Lip Surgery'=>'lips.jpg','Facial Contouring'=>'contour.jpg','Face Lifting'=>'lifting.jpg','Liposuction'=>'body.jpg','Breast Surgery'=>'breast.jpg','Other Procedures'=>'other.jpg','Chin Surgery'=>'contour.jpg','Facial Fat Reduction'=>'lifting.jpg','Skin Treatments'=>'other.jpg'];
  $hospitalCount = \App\Models\User::where('role','hospital')->count();
  $requests = TreatmentRequest::where('user_id',$request->user()->id)->with(['photos','quotations','booking'])->latest()->get()->map(fn ($item) => [
   'id'=>sprintf('REQ-%s-%06d',$item->created_at->format('Y'),$item->id), 'procedure_name'=>$item->procedure_label, 'procedures'=>$item->procedure_names, 'procedure_image'=>$images[$item->procedure] ?? 'other.jpg',
   'submitted_at'=>$item->created_at->format('Y-m-d'), 'preferred_date'=>$item->preferred_period, 'preferred_period_label'=>$item->preferred_period_label,
   'treatment_deposit'=>$item->booking && $item->booking->is_payment_eligible && $item->user_id && $item->payment_confirmed_at ? (new \App\Services\DepositPayment)->treatmentSummary($item->booking) : null,
   'selected_quotation_id'=>$item->booking?->quotation_id,
   'status'=>$item->customer_status,'payment_is_simulated'=>(bool)$item->booking?->payment_is_simulated, 'hospitals_contacted'=>max($item->quotations->count(), $item->selected_hospital_id ? 1 : ($item->selected_hospital_name ? 0 : $hospitalCount)),
   'hospital_responses'=>$item->quotations->map(fn($q)=>['quotation_id'=>$q->id,'response_status'=>'quote_received','quotation_amount'=>(float)$q->amount,'responded_at'=>$q->updated_at->format('Y-m-d'),'details'=>$q->details,'valid_until'=>$q->valid_until?->format('Y-m-d'),'payment_url'=>$item->booking?->quotation_id===$q->id && $item->booking->is_payment_eligible && $item->booking->status!=='Awaiting Acceptance' ? route('bookings.deposit',$item->booking) : null,'accept_url'=>(!$item->booking || ($item->booking->status==='Awaiting Acceptance' && $item->booking->deposit_status==='Pending' && $item->booking->quotation_id===$q->id)) && !$item->payment_confirmed_at && !$item->selected_hospital_id && !$item->selected_hospital_name && $item->status!=='cancelled' && (!$q->valid_until || !$q->valid_until->endOfDay()->isPast()) ? route('quotations.accept',$q) : null])->values(), 'name'=>$item->name, 'email'=>$item->email,
   'date_of_birth'=>$item->date_of_birth,'additional_notes'=>$item->additional_notes,'line_id'=>$item->line_id,'phone_number'=>$item->phone_number,'age'=>$item->age,'gender'=>$item->gender, 'goals'=>$item->goals, 'medical_information'=>$item->medical_information,
   'photos'=>$item->photos->map(fn ($photo)=>['url'=>route('requests.photos.show',$photo),'name'=>$photo->original_name])->values(),
  ])->values();
  $customerBookings=\App\Models\Booking::bookingEligible()->where('user_id',$request->user()->id)->with(['hospital','quotation','treatmentRequest'])->latest()->get();
  return response()->view('requests.index',compact('requests','customerBookings'))->header('Cache-Control','private, no-store');
 }
 public function store(Request $request) {
  app()->setLocale('en');
  if (is_string($request->input('phone_number'))) $request->merge(['phone_number'=>preg_replace('/[\\s().-]/','',$request->input('phone_number'))]);
  $data=$request->validate([
   'name'=>['required','string','max:80'], 'email'=>['required','email','max:254'], 'procedure'=>['nullable',Rule::in(self::PROCEDURES)],
   'procedures'=>['required_without:procedure','array','min:1','max:11'], 'procedures.*'=>['required','string','distinct',Rule::in(self::PROCEDURES)],
   'line_id'=>['nullable','string','max:100'],'phone_number'=>['nullable','string','regex:/^\\+[1-9][0-9]{6,14}$/'],
   'age'=>['nullable','integer','min:18','max:120'],'gender'=>['nullable',Rule::in(['Female','Male'])],
   'date_of_birth'=>['nullable','date_format:Y-m-d','before_or_equal:today','after_or_equal:'.now()->subYears(120)->format('Y-m-d')],
   'preferred_surgery_month'=>['nullable','required_with:preferred_surgery_year','integer','between:1,12'],
   'preferred_surgery_year'=>['nullable','required_with:preferred_surgery_month','integer','min:'.now()->year,'max:'.(now()->year+10)],
   'goals'=>['nullable','string','max:1000'], 'preferred_surgery_date'=>['nullable','date_format:Y-m-d','after_or_equal:today'],
   'medical_information'=>['nullable','string','max:3000'], 'consent_to_share'=>['accepted'],
   'photos'=>['nullable','array','max:10'], 'photos.*'=>['required','file','image','mimes:jpg,jpeg,png,webp','max:5120'],
  ]);
  if (!empty($data['preferred_surgery_month']) && !empty($data['preferred_surgery_year']) && sprintf('%04d-%02d',$data['preferred_surgery_year'],$data['preferred_surgery_month']) < now()->format('Y-m')) {
   throw \Illuminate\Validation\ValidationException::withMessages(['preferred_surgery_month'=>'Please select the current month or a future month.']);
  }
  $selected=$data['procedures'] ?? [$data['procedure']];
  if (!empty($data['date_of_birth'])) $data['age']=\Carbon\Carbon::parse($data['date_of_birth'])->age;
  $paths=[];
  try {
   DB::transaction(function () use ($request,$data,$selected,&$paths) {
    $item=TreatmentRequest::create([
     'reference'=>(string) Str::uuid(), 'user_id'=>$request->user()->id, 'name'=>$data['name'], 'email'=>$data['email'],
     'procedure'=>$selected[0],'procedures'=>$selected, 'line_id'=>$data['line_id'] ?? null,'phone_number'=>$data['phone_number'] ?? null,'age'=>$data['age'] ?? null,'gender'=>$data['gender'] ?? null,'preferred_surgery_month'=>$data['preferred_surgery_month'] ?? null,'preferred_surgery_year'=>$data['preferred_surgery_year'] ?? null, 'goals'=>$data['goals'] ?? null, 'preferred_surgery_date'=>$data['preferred_surgery_date'] ?? null,
     'medical_information'=>$data['medical_information'] ?? null, 'status'=>'pending','consented_at'=>now(),'consent_text'=>self::CONSENT,
     'date_of_birth'=>$data['date_of_birth'] ?? null,
    ]);
    $user=$request->user();
    $user->name=$data['name'];
    foreach (['phone_number','line_id','date_of_birth','gender'] as $field) {
     if (array_key_exists($field,$data)) $user->{$field}=$data[$field];
    }
    $user->save();
    foreach ($request->file('photos',[]) as $file) {
     $path=$file->store($item->reference,'request_photos'); $paths[]=$path;
     $item->photos()->create(['path'=>$path,'original_name'=>Str::limit(basename($file->getClientOriginalName()),200,''),'mime_type'=>$file->getMimeType(),'size'=>$file->getSize()]);
    }
   });
  } catch (Throwable $exception) {
   Storage::disk('request_photos')->delete($paths);
   return response()->json(['message'=>'Your request could not be saved. Please try again.'],500);
  }
  return response()->json(['message'=>'Your request has been saved and is available to partner hospitals.','redirect'=>route('requests.index')],201);
 }
 public function photo(Request $request, RequestPhoto $photo) {
  abort_unless($photo->treatmentRequest->user_id === $request->user()->id || $request->user()->isAdmin() || ($request->user()->isHospital() && TreatmentRequest::availableToHospital($request->user()->id)->whereKey($photo->treatment_request_id)->exists()),404);
  abort_unless(Storage::disk('request_photos')->exists($photo->path),404);
  return Storage::disk('request_photos')->response($photo->path,'photo.'.pathinfo($photo->path,PATHINFO_EXTENSION),['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff'],'inline');
 }
}
