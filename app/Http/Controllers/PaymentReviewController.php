<?php
namespace App\Http\Controllers;
use App\Models\{Booking,TreatmentRequest,PaymentSubmission};
use App\Services\GuestActivationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};

class PaymentReviewController extends Controller {
 public function confirm(Request $request,Booking $booking){
  // Compatibility for old booking links. Missing stage always means Step 4, never Step 9.
  $request->validate(['payment_stage'=>'sometimes|in:initial,treatment']);
  return $this->confirmSelected($request,$booking,null);
 }
 public function confirmTransaction(Request $request,PaymentSubmission $submission){
  return $this->confirmSelected($request,$submission->booking,$submission);
 }
 private function confirmSelected(Request $request,Booking $booking,?PaymentSubmission $selected){
  abort_unless($request->user()?->isAdmin(),403);
  abort_unless(PaymentSubmission::available(),503);
  $request->validate(['confirmation'=>'required|accepted','funds_verified'=>'required|accepted']);
  $item=DB::transaction(function()use($request,$booking,$selected){
   $item=TreatmentRequest::whereKey($booking->treatment_request_id)->lockForUpdate()->firstOrFail();
   $row=Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
   abort_unless($selected || $row->deposit_phase===$request->input('payment_stage','initial'),409,'The payment stage changed. Open the selected transaction again.');
   abort_unless(!$row->is_test&&!$row->payment_is_simulated&&$item->status!=='cancelled'&&$row->status==='Pending Payment'&&in_array($row->deposit_status,['Pending','Pending Verification','Rejected','Information Required'],true),409,'This payment is no longer eligible for confirmation.');
   abort_unless($row->deposit_amount_satang>0&&$item->selected_hospital_id===$row->hospital_id&&$row->quotation?->hospital_id===$row->hospital_id&&$row->quotation?->treatment_request_id===$item->id&&$row->deposit_amount_satang<=(int)round((float)$row->quotation->amount*100),422,'Booking and deposit must match the accepted quotation.');
   $submission=$selected ? PaymentSubmission::whereKey($selected->id)->lockForUpdate()->firstOrFail() : $row->paymentSubmissions()->where('purpose',$row->deposit_phase)->whereIn('status',['pending','information_requested'])->first();
   if(!$submission)$submission=$row->paymentSubmissions()->create(['purpose'=>$row->deposit_phase,'amount_satang'=>$row->deposit_amount_satang,'method'=>'Admin bank review','status'=>'pending','submitted_at'=>now()]);
   abort_unless($submission->booking_id===$row->id && in_array($submission->status,['pending','information_requested'],true),409,'This transaction has already been reviewed.');
   abort_if($row->paymentSubmissions()->where('purpose',$submission->purpose)->where('status','approved')->exists(),409,'This payment stage has already been approved.');
   abort_unless($submission->purpose===$row->deposit_phase,422,'Payment phase does not match.');
   abort_unless($submission->amount_satang===$row->deposit_amount_satang,422,'Payment amount does not match the deposit.');
   (new \App\Services\PaymentLedger)->validateTreatmentPayment($row,$submission);
   $notes='Admin confirmed independent verification of received funds. No customer upload required.';
   $submission->update(['status'=>'approved','method'=>$submission->method==='Not submitted'?'Admin bank review':$submission->method,'reviewer_id'=>$request->user()->id,'reviewed_at'=>now(),'review_notes'=>$notes]);
   $submission->reviews()->create(['reviewer_id'=>$request->user()->id,'decision'=>'approved','notes'=>$notes,'created_at'=>now()]);
   $row->update(['deposit_status'=>'Paid','status'=>$row->deposit_phase==='treatment'?'Confirmed':'Awaiting Hospital Confirmation','payment_verified_at'=>now(),'payment_verified_by'=>$request->user()->id]);
   $item->update($row->deposit_phase==='treatment' ? ['status'=>'confirmed'] : ['payment_confirmed_at'=>now(),'status'=>'awaiting_hospital_confirmation']);return $item;
  });
  $message=$this->sendApprovalNotification($item,'Payment confirmed.');
  return back()->with('status',$message);
 }
 public function proof(Request $request,PaymentSubmission $submission){
  abort_unless($request->user()?->isAdmin(),403);
  abort_unless(PaymentSubmission::available(),503,'Payment review is temporarily unavailable.');
  abort_unless($submission->proof_path&&Storage::disk('request_photos')->exists($submission->proof_path),404);
  return Storage::disk('request_photos')->download($submission->proof_path,$submission->proof_name,['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
 }
 public function requestEvidence(Request $request,Booking $booking){
  abort_unless($request->user()?->isAdmin(),403);abort_unless(PaymentSubmission::available(),503);
  $data=$request->validate(['confirmation'=>'accepted','notes'=>'required|string|max:2000']);
  $item=DB::transaction(function()use($request,$booking,$data){
   $item=TreatmentRequest::whereKey($booking->treatment_request_id)->lockForUpdate()->firstOrFail();
   $row=Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
   abort_if($row->is_test||$row->payment_is_simulated||$item->status==='cancelled'||!in_array($row->status,['Pending Payment'],true)||in_array($row->deposit_status,['Paid','Refunded'],true),409,'This booking cannot request payment evidence.');
   abort_unless($row->deposit_amount_satang!==null&&$row->deposit_amount_satang>0,422,'Record the deposit amount before requesting payment evidence.');
   $submission=$row->paymentSubmissions()->where('purpose',$row->deposit_phase)->first();
   if(!$submission||!in_array($submission->status,['pending','information_requested'],true))$submission=$row->paymentSubmissions()->create(['purpose'=>$row->deposit_phase,'amount_satang'=>$row->deposit_amount_satang,'method'=>'Not submitted','status'=>'information_requested','submitted_at'=>now()]);
   $submission->update(['status'=>'information_requested','reviewer_id'=>$request->user()->id,'reviewed_at'=>now(),'review_notes'=>$data['notes']]);
   $submission->reviews()->create(['reviewer_id'=>$request->user()->id,'decision'=>'information_requested','notes'=>$data['notes'],'created_at'=>now()]);
   $row->update(['deposit_status'=>'Information Required']);return $item;
  });
  try{
   $url=\Illuminate\Support\Facades\URL::temporarySignedRoute('guest.requests.access',now()->addDays(7),['reference'=>$item->reference]);
   if(!app()->runningUnitTests()&&config('mail.default')!=='smtp')throw new \RuntimeException('SMTP is not configured.');
   \Illuminate\Support\Facades\Notification::route('mail',$item->email)->notify(new \App\Notifications\PaymentEvidenceRequested($url,$data['notes']));
   $message='Payment evidence requested. Private return link emailed to the customer.';
  }catch(\Throwable $e){$message='Evidence request saved. Email delivery failed; the request is visible on the customer?s private booking page.';}
  return back()->with('status',$message);
 }
 public function review(Request $request,PaymentSubmission $submission){
  abort_unless($request->user()?->isAdmin(),403);
  if(is_string($request->input('verification_reference')))$request->merge(['verification_reference'=>strtoupper(trim($request->input('verification_reference')))]);
  $data=$request->validate(['decision'=>'required|in:approved,rejected,information_requested','confirmation'=>'accepted',
   'notes'=>'required|string|max:2000','funds_verified'=>'exclude_unless:decision,approved|required|accepted',
   'verification_reference'=>'required_if:decision,approved|nullable|string|max:200|unique:payment_reviews,verification_reference']);
  $approved=DB::transaction(function()use($submission,$data,$request){
   $item=TreatmentRequest::whereKey($submission->booking->treatment_request_id)->lockForUpdate()->firstOrFail();
   $booking=Booking::whereKey($submission->booking_id)->lockForUpdate()->firstOrFail();
   $row=PaymentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
   abort_unless($row->purpose===$booking->deposit_phase,422,'Payment phase does not match.');
   abort_unless($row->status==='pending'&&$booking->deposit_status==='Pending Verification'&&$booking->status==='Pending Payment',409,'This submission has already been reviewed or is no longer eligible.');
   abort_if($booking->is_test||$booking->payment_is_simulated||$item->status==='cancelled',422,'Only a real active booking can be reviewed here.');
   abort_unless($booking->deposit_amount_satang!==null&&$booking->deposit_amount_satang>0&&$row->amount_satang===$booking->deposit_amount_satang,422,'A matching recorded deposit is required.');
   abort_unless($item->selected_hospital_id===$booking->hospital_id&&$booking->quotation?->hospital_id===$booking->hospital_id&&$booking->quotation?->treatment_request_id===$item->id&&$booking->deposit_amount_satang<=(int)round((float)$booking->quotation->amount*100),422,'Booking and quotation do not match.');
   if($data['decision']==='approved'){
    abort_if($booking->paymentSubmissions()->where('purpose',$row->purpose)->where('status','approved')->exists(),409,'This payment stage has already been approved.');
    (new \App\Services\PaymentLedger)->validateTreatmentPayment($booking,$row);
   }
   $row->update(['status'=>$data['decision'],'reviewer_id'=>$request->user()->id,'reviewed_at'=>now(),'review_notes'=>$data['notes']]);
   $row->reviews()->create(['reviewer_id'=>$request->user()->id,'decision'=>$data['decision'],'notes'=>$data['notes'],
    'verification_reference'=>$data['decision']==='approved'?trim($data['verification_reference']):null,'created_at'=>now()]);
   $booking->deposit_status=match($data['decision']){'approved'=>'Paid','rejected'=>'Rejected',default=>'Information Required'};
   if($data['decision']==='approved'){
    $booking->status=$booking->deposit_phase==='treatment'?'Confirmed':'Awaiting Hospital Confirmation';$booking->payment_verified_at=now();$booking->payment_verified_by=$request->user()->id;
    $item->update($booking->deposit_phase==='treatment' ? ['status'=>'confirmed'] : ['payment_confirmed_at'=>now(),'status'=>'awaiting_hospital_confirmation']);
   }
   $booking->save();return $data['decision']==='approved'?$item:null;
  });
  $message='Payment review saved.';
  if($approved)$message=$this->sendApprovalNotification($approved,$message);
  return back()->with('status',$message);
 }
 private function sendApprovalNotification(TreatmentRequest $item,string $message):string {
  $booking=$item->booking()->firstOrFail();
  if($booking->deposit_phase==='treatment'){
   $delivery=app(\App\Services\BookingDepositEmailNotification::class)->send($booking);
   return $message.(in_array($delivery,['sent','already_sent'],true)
    ? ' Booking confirmed. Confirmation email sent to the customer.'
    : ' Booking confirmed, but the confirmation email could not be sent. Please check mail delivery settings.');
  }
  if(!$item->user_id){
   try{app(GuestActivationCode::class)->send($item);$message.=' Verification email sent.';}
   catch(\Throwable $e){$message.=' Payment approval is saved, but email could not be sent. The customer can resend their code from the private request page.';}
  }
  return $message;
 }
}
