<?php
namespace App\Http\Controllers;

use App\Models\{TreatmentRequest,Quotation,Booking,User,RequestPhoto};
use App\Notifications\{GuestRequestAccess,GuestJourneyCode};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage,Cache,Hash,Notification,URL,RateLimiter,Auth};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuestTreatmentRequestController extends Controller
{
 public function store(Request $request){
  app()->setLocale('en');
  $request->merge(['email'=>Str::lower(trim((string)$request->input('email'))),'phone_number'=>preg_replace('/[\s().-]/','',(string)$request->input('phone_number'))]);
  $data=$request->validate([
   'submission_reference'=>'required|uuid','name'=>'required|string|max:80','email'=>'required|email|max:254',
   'line_id'=>'nullable|string|max:100','phone_number'=>['nullable','regex:/^\+[1-9][0-9]{6,14}$/'],
   'date_of_birth'=>'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:'.now()->subYears(120)->format('Y-m-d'),
   'gender'=>['required',Rule::in(['Female','Male'])],
   'procedures'=>'required|array|min:1|max:11','procedures.*'=>['required','distinct',Rule::in(TreatmentRequestController::PROCEDURES)],
   'goals'=>'required|string|max:1000','additional_notes'=>'nullable|string|max:3000','medical_information'=>'nullable|string|max:3000',
   'preferred_surgery_month'=>'required|integer|between:1,12','preferred_surgery_year'=>'required|integer|min:'.now()->year.'|max:'.(now()->year+10),
   'consent_to_share'=>'accepted','photos'=>'nullable|array|max:10','photos.*'=>'required|file|image|mimes:jpg,jpeg,png,webp|max:5120',
  ]);
  if(sprintf('%04d-%02d',$data['preferred_surgery_year'],$data['preferred_surgery_month'])<now()->format('Y-m'))throw ValidationException::withMessages(['preferred_surgery_month'=>'Select the current month or a future month.']);
  $reference=$data['submission_reference'];$paths=[];$created=false;
  try{
   $item=Cache::lock('guest-submit:'.hash('sha256',$request->session()->getId().$reference),20)->block(5,function()use($request,$data,$reference,&$paths,&$created){
    if($request->session()->get('real_guest_submitted.'.$reference))return TreatmentRequest::where('reference',$reference)->firstOrFail();
    abort_if(TreatmentRequest::where('reference',$reference)->exists(),409,'Please refresh the request form and try again.');
    return DB::transaction(function()use($request,$data,$reference,&$paths,&$created){
     $owner=$request->user();$ownerId=$owner&&Str::lower($owner->email)===$data['email']?$owner->id:null;
     $item=TreatmentRequest::create(['reference'=>$reference,'user_id'=>$ownerId,'name'=>$data['name'],'email'=>$data['email'],
      'procedures'=>$data['procedures'],'procedure'=>$data['procedures'][0],'line_id'=>$data['line_id']??null,'phone_number'=>$data['phone_number']?:null,
      'date_of_birth'=>$data['date_of_birth'],'age'=>\Carbon\Carbon::parse($data['date_of_birth'])->age,'gender'=>$data['gender'],
      'goals'=>$data['goals'],'additional_notes'=>$data['additional_notes']??null,'medical_information'=>$data['medical_information']??null,
      'preferred_surgery_month'=>$data['preferred_surgery_month'],'preferred_surgery_year'=>$data['preferred_surgery_year'],
      'status'=>'pending','consented_at'=>now(),'consent_text'=>TreatmentRequestController::CONSENT]);
     foreach($request->file('photos',[]) as $file){$path=$file->store($item->reference,'request_photos');$paths[]=$path;$item->photos()->create(['path'=>$path,'original_name'=>Str::limit(basename($file->getClientOriginalName()),200,''),'mime_type'=>$file->getMimeType(),'size'=>$file->getSize()]);}
     $created=true;return $item;
    });
   });
  }catch(\Throwable $error){Storage::disk('request_photos')->delete($paths);throw $error;}
  $request->session()->put('real_guest_submitted.'.$reference,true);
  $request->session()->put('real_guest.'.$reference,now()->addDays(7)->timestamp);app(\App\Services\GuestBookingResume::class)->remember($request,$reference);
  $mailSent=false;
  if($created){try{$this->sendAccess($item);$mailSent=true;}catch(\Throwable $error){/* Keep the saved request. Never print or log SMTP exceptions. */}}
  return response()->json(['reference'=>$this->label($item),'redirect'=>route('guest.requests.show',$reference),'mail_sent'=>$mailSent,
   'message'=>$mailSent?'Your request has been saved. A private access link has been sent to your email.':'Your request has been saved. If the email does not arrive, use Resend access link on your request page.'],201)->header('Cache-Control','no-store');
 }
 public function verificationEntry(Request $request,string $reference){
  $item=TreatmentRequest::where('reference',$reference)->firstOrFail();$this->paid($item);
  $request->session()->regenerate();
  $request->session()->put('real_guest.'.$reference,now()->addDays(7)->timestamp);
  app(\App\Services\GuestBookingResume::class)->remember($request,$reference);
  return $this->show($request,$reference);
 }
 public function access(Request $request,string $reference){$item=TreatmentRequest::where('reference',$reference)->firstOrFail();$request->session()->regenerate();$request->session()->put('real_guest.'.$reference,now()->addDays(7)->timestamp);app(\App\Services\GuestBookingResume::class)->remember($request,$reference);return redirect()->route('guest.requests.show',$reference)->header('Referrer-Policy','no-referrer');}
 private function owned(Request $request,string $reference):TreatmentRequest{
  abort_unless($request->session()->get('real_guest.'.$reference,0)>now()->timestamp||($request->user()&&TreatmentRequest::where('reference',$reference)->where('user_id',$request->user()->id)->exists()),403,'Open the private request link in your email to continue.');
  return TreatmentRequest::where('reference',$reference)->firstOrFail();
 }
 public function resume(Request $request){
  $service=app(\App\Services\GuestBookingResume::class);$references=$service->references($request);
  abort_unless($references,403,'Open the private return link in your email to resume your booking.');
  $reference=$request->query('request');
  if(!$reference&&count($references)>1){
   $items=TreatmentRequest::whereIn('reference',array_keys($references))->with('booking')->latest()->get();
   return response()->view('requests.guest-resume',compact('items'))->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
  }
  $reference=$reference?:array_key_last($references);abort_unless(is_string($reference)&&isset($references[$reference]),403);
  $item=TreatmentRequest::where('reference',$reference)->with('booking')->firstOrFail();
  $request->session()->put('real_guest.'.$reference,min((int)$references[$reference],now()->addMinutes(config('session.lifetime'))->timestamp));
  if($item->user_id)return redirect()->route($request->user()?->id===$item->user_id?'requests.index':'login');
  $progress=$service->state($request,$item);
  return redirect()->to(route('guest.requests.show',$reference).'#'.$progress['anchor'])->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
 }
 public function show(Request $request,string $reference){$item=$this->owned($request,$reference)->load(['photos','quotations.hospital','booking']);$paymentReviewReady=\App\Models\PaymentSubmission::available();if($item->booking){if($paymentReviewReady)$item->booking->load('paymentSubmissions');else $item->booking->setRelation('paymentSubmissions',collect());}$hasWithdrawnQuotations=Quotation::where('treatment_request_id',$item->id)->whereNotNull('withdrawn_at')->exists();$progress=app(\App\Services\GuestBookingResume::class)->state($request,$item);return response()->view('requests.guest',compact('item','hasWithdrawnQuotations','progress','paymentReviewReady'))->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');}
 public function photo(Request $request,string $reference,RequestPhoto $photo){$item=$this->owned($request,$reference);abort_unless($photo->treatment_request_id===$item->id,404);abort_unless(Storage::disk('request_photos')->exists($photo->path),404);return Storage::disk('request_photos')->response($photo->path,'photo.'.pathinfo($photo->path,PATHINFO_EXTENSION),['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff'],'inline');}
 public function resend(Request $request,string $reference){$item=$this->owned($request,$reference);$key='real-guest-mail:'.hash('sha256',$item->email);if(RateLimiter::tooManyAttempts($key,1))return back()->withErrors(['email'=>'Please wait one minute before resending.']);RateLimiter::hit($key,60);try{$this->sendAccess($item);return back()->with('status','The private access link has been sent. Check your inbox and spam folder.');}catch(\Throwable $error){return back()->withErrors(['email'=>'Email could not be sent. Your request is saved; please try again later.']);}}
 private function sendAccess(TreatmentRequest $item):void{$url=URL::temporarySignedRoute('guest.requests.access',now()->addDays(7),['reference'=>$item->reference]);$this->send($item->email,new GuestRequestAccess($url,$this->label($item),$item->preferred_period_label,true));}
 private function send(string $email,$notification):void{if(!app()->runningUnitTests()&&config('mail.default')!=='smtp')throw new \RuntimeException('SMTP is not configured.');Notification::route('mail',$email)->notify($notification);}
 private function label(TreatmentRequest $item):string{return sprintf('REQ-%s-%06d',$item->created_at->format('Y'),$item->id);}
 public function accept(Request $request,string $reference,Quotation $quotation){
  $item=$this->owned($request,$reference);$expected=$request->validate(['expected_total_satang'=>'required|integer|min:1']);
  DB::transaction(function()use($item,$quotation,$expected){
   $item=TreatmentRequest::whereKey($item->id)->lockForUpdate()->firstOrFail();$q=Quotation::whereKey($quotation->id)->where('treatment_request_id',$item->id)->whereNull('withdrawn_at')->lockForUpdate()->firstOrFail();
   $existing=$item->booking()->first();if($existing){abort_unless($existing->quotation_id===$q->id&&$existing->hospital_id===$q->hospital_id,422,'A different quotation has already been accepted.');return;}
   abort_if($item->status==='cancelled'||!$q->hospital?->isHospital()||($q->valid_until&&$q->valid_until->endOfDay()->isPast()),422,'This quotation is no longer available.');
   [$whole,$fraction]=explode('.',$q->amount);$total=(int)$whole*100+(int)$fraction;$deposit=\App\Services\DepositPayment::calculate($total);abort_unless($total===(int)$expected['expected_total_satang'],422,'The quotation price changed. Please reload and review it again.');
   $item->update(['status'=>'pending_payment','selected_hospital_id'=>$q->hospital_id,'selected_hospital_name'=>$q->hospital->name,'hospital_approved_at'=>now()]);
   $booking=Booking::create(['treatment_request_id'=>$item->id,'quotation_id'=>$q->id,'hospital_id'=>$q->hospital_id,'user_id'=>$item->user_id,'status'=>'Pending Payment','deposit_status'=>'Pending','deposit_amount_satang'=>$deposit,'is_test'=>false,'payment_is_simulated'=>false]);
   (new \App\Services\PaymentLedger)->ensureCurrent($booking);
  });
  return redirect()->route('guest.requests.show',$reference)->with('status','Quotation accepted. Your booking is pending verified deposit payment.');
 }
 public function claimPayment(Request $request,string $reference){
  $item=$this->owned($request,$reference);
  abort_unless(\App\Models\PaymentSubmission::available(),503,'Payment submissions are temporarily unavailable. Your booking is saved.');
  $data=$request->validate(['method'=>'sometimes|in:Bank Transfer,PromptPay,Thai QR','transaction_reference'=>'nullable|string|max:200',
   'proof'=>'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
  $path=null;
  try{DB::transaction(function()use($item,$request,$data,&$path){
   $locked=TreatmentRequest::whereKey($item->id)->lockForUpdate()->firstOrFail();
   $row=Booking::where('treatment_request_id',$item->id)->lockForUpdate()->firstOrFail();
   abort_unless(in_array($row->deposit_status,['Pending','Pending Verification','Rejected','Information Required','Failed'],true)&&$row->status==='Pending Payment'&&$locked->status!=='cancelled',422,'This payment cannot be submitted.');
   $submission=$row->paymentSubmissions()->where('purpose',$row->deposit_phase)->where('status','pending')->first();
   if($submission && $row->deposit_status==='Pending Verification')return;
   abort_unless($row->deposit_amount_satang!==null&&$row->deposit_amount_satang>0,422,'Deposit amount is not recorded. Contact support.');
   if($request->hasFile('proof'))$path=$request->file('proof')->store('payment-proofs/'.Str::uuid(),'request_photos');
   $attributes=['purpose'=>$row->deposit_phase,'amount_satang'=>$row->deposit_amount_satang,'method'=>$data['method']??'Bank Transfer',
    'transaction_reference'=>trim($data['transaction_reference']??'')?:null,'proof_path'=>$path,
    'proof_name'=>$path?Str::limit(basename($request->file('proof')->getClientOriginalName()),190,''):null,'status'=>'pending','submitted_at'=>now()];
   if($submission)$submission->update($attributes);else $row->paymentSubmissions()->create($attributes);
   $row->update(['deposit_status'=>'Pending Verification']);
  });}catch(\Throwable $e){if($path)Storage::disk('request_photos')->delete($path);throw $e;}
  if($request->expectsJson())return response()->json(['message'=>'Payment submitted. Pending Admin Approval; please do not pay again.','payment_status'=>'Pending Verification']);
  return redirect()->to(route('guest.requests.show',$reference).'#booking-deposit')->with('status','Payment submitted. Pending Admin Approval; please do not pay again.');
 }
 private function paid(TreatmentRequest $item):Booking{$booking=$item->booking;abort_unless($booking&&$booking->deposit_status==='Paid'&&!$booking->is_test&&!$booking->payment_is_simulated&&$booking->payment_verified_at&&$item->payment_confirmed_at,422,'A real, verified deposit payment is required before account activation.');return $booking;}
 public function code(Request $request,string $reference){
  $item=$this->owned($request,$reference);$this->paid($item);
  try{app(\App\Services\GuestActivationCode::class)->send($item);}
  catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){throw $e;}
  catch(\Throwable $e){return back()->withErrors(['code'=>'Email could not be sent. Your payment approval is saved; please try again later.']);}
  $message='Verification email sent. The code expires in 10 minutes.';
  return $request->expectsJson()?response()->json(['message'=>$message,'cooldown'=>60]):back()->with('status',$message);
 }
 public function verify(Request $request,string $reference){
  $item=$this->owned($request,$reference);$booking=$this->paid($item);
  if($request->has('digits')){$digits=$request->validate(['digits'=>'required|array|size:6','digits.*'=>'required|regex:/^[0-9]$/']);$request->merge(['code'=>implode('',$digits['digits'])]);}
  $data=$request->validate(['code'=>'required|digits:6']);$key='real-guest-code:'.$reference;
  Cache::lock($key.':lock',10)->block(3,function()use($request,$item,$booking,$data,$key){
   Cache::lock($key.':record',10)->block(3,function()use($request,$item,$booking,$data,$key){
    abort_if(RateLimiter::tooManyAttempts($key.':attempts',5),429,'Too many attempts. Please resend the code.');RateLimiter::hit($key.':attempts',300);
    $code=Cache::get($key);
    if(!$code||($code['booking']??null)!==$booking->id||($code['email']??null)!==$item->email||$code['expires']<=now()->timestamp)throw ValidationException::withMessages(['code'=>'The code is incorrect or expired.']);
    abort_if(($code['attempts']??0)>=5,429,'Too many attempts. Please resend the code.');
    $code['attempts']=($code['attempts']??0)+1;Cache::put($key,$code,\Carbon\Carbon::createFromTimestamp($code['expires']));
    if(!Hash::check($data['code'],$code['hash']))throw ValidationException::withMessages(['code'=>'The code is incorrect or expired.']);
    Cache::forget($key);$request->session()->regenerate();$request->session()->put('real_guest_verified.'.$item->reference,['booking'=>$booking->id,'email'=>$item->email,'expires'=>now()->addMinutes(10)->timestamp]);
   });
  });
  $url=route('guest.requests.account',$reference);
  if(User::where('email',$item->email)->exists()){$request->session()->put('real_guest_link',$reference);}
  return $request->expectsJson()?response()->json(['message'=>'Email Verified Successfully','redirect'=>$url]):redirect()->to($url)->with('status','Email Verified Successfully');
 }
 public function account(Request $request,string $reference){
  $item=$this->owned($request,$reference);$this->paid($item);
  $progress=app(\App\Services\GuestBookingResume::class)->state($request,$item);abort_unless($progress['verified_email'],403,'Verify your email first.');
  if($item->user_id)return redirect()->route($request->user()?->id===$item->user_id?'requests.index':'login');
  if(User::where('email',$item->email)->exists())$request->session()->put('real_guest_link',$reference);
  return response()->view('requests.guest-account',compact('item'))->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
 }
 public function activate(Request $request,string $reference){$item=$this->owned($request,$reference);$booking=$this->paid($item);$grant=$request->session()->get('real_guest_verified.'.$reference);abort_unless($grant&&$grant['expires']>now()->timestamp&&$grant['booking']===$booking->id&&$grant['email']===$item->email,403,'Verify the emailed code first.');
  if(User::where('email',$item->email)->exists()){$request->session()->put('real_guest_link',$reference);return redirect()->route('login')->with('status','Sign in to your existing account to link this verified request.');}
  $data=$request->validate(['username'=>['sometimes','email',Rule::in([$item->email])],'password'=>['required','string','confirmed',\Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers(),function($attribute,$value,$fail){if(strlen($value)>72)$fail('The password must not exceed 72 bytes.');}],'terms'=>'accepted']);
  $user=DB::transaction(function()use($item,$booking,$data){$locked=TreatmentRequest::whereKey($item->id)->lockForUpdate()->firstOrFail();abort_if($locked->user_id!==null||User::where('email',$item->email)->exists(),409,'This email already has an account. Please sign in.');$user=User::create(['name'=>$item->name,'email'=>$item->email,'password'=>$data['password']]);$user->forceFill(['email_verified_at'=>now(),'phone_number'=>$item->phone_number,'line_id'=>$item->line_id,'date_of_birth'=>$item->date_of_birth,'gender'=>$item->gender])->save();$locked->update(['user_id'=>$user->id]);$booking->update(['user_id'=>$user->id]);return $user;});
  $request->session()->forget('real_guest_verified.'.$reference);$request->session()->regenerate();return redirect()->route('login')->with('status','Your account is ready. Sign in with your verified email and password. Your request and booking are linked.');
 }
}
