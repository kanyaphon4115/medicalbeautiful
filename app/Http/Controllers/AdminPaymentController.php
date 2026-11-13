<?php
namespace App\Http\Controllers;
use App\Models\{Booking,TreatmentRequest};
use App\Services\DepositPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPaymentController extends Controller {
 public function pageData(Request $request): array {
  abort_unless($request->user()->isAdmin(),403);
  $filters=$request->validate(['search'=>['nullable','string','max:200'],'status'=>['nullable','in:Pending,Pending Payment,Pending Verification,Rejected,Information Required,Paid,Failed,Expired,Refunded,Awaiting Hospital Confirmation,Confirmed,Completed,Cancelled'],
   'stage'=>['nullable','in:all,initial,treatment'],'payment'=>['nullable','integer','min:1'],'legacy'=>['nullable','integer','min:1'],
   'from'=>['nullable','date_format:Y-m-d'],'to'=>['nullable','date_format:Y-m-d'],
   'method'=>['nullable','in:unrecorded,Bank Transfer,PromptPay,Thai QR'],'record'=>['nullable','integer','min:1'],'hospital'=>['nullable','string','max:200'],'size'=>['nullable','in:5,10,25'],'records'=>['nullable','in:all,real,test']]);
  if(!empty($filters['from']) && !empty($filters['to']) && $filters['to']<$filters['from'])throw \Illuminate\Validation\ValidationException::withMessages(['to'=>'The end date must be on or after the start date.']);
  $local=app()->environment(['local','testing']);$mode=$local?($filters['records']??'all'):'real';
  $base=Booking::bookingEligible();if($mode==='real')$base->where('is_test',false);elseif($mode==='test')$base->where('is_test',true);
  $payments=(clone $base)->paymentEligible();
  $rows=($request->route('page')==='bookings' ? clone $base : clone $payments)->with(['customer','hospital','quotation','treatmentRequest','verifier']);
  $paymentReviewReady=\App\Models\PaymentSubmission::available();if($paymentReviewReady)$rows->with(['paymentSubmissions.reviews.reviewer','paymentSubmissions.reviewer']);

  if($filters['record']??null)$rows->whereKey($filters['record']);
  if($hospital=trim($filters['hospital']??''))$rows->whereHas('hospital',fn($q)=>$q->where('name','like',"%$hospital%"));
  if($paymentReviewReady&&($filters['method']??null)&&$filters['method']!=='unrecorded')$rows->whereHas('paymentSubmissions',fn($q)=>$q->where('method',$filters['method']));
  elseif($paymentReviewReady&&($filters['method']??null)==='unrecorded')$rows->whereDoesntHave('paymentSubmissions');
  if($search=trim($filters['search']??''))$rows->where(function($q)use($search){
   $q->whereHas('customer',fn($q)=>$q->where('name','like',"%$search%")->orWhere('email','like',"%$search%"))
    ->orWhereHas('hospital',fn($q)=>$q->where('name','like',"%$search%"))
    ->orWhereHas('treatmentRequest',fn($q)=>$q->where('name','like',"%$search%")->orWhere('email','like',"%$search%"));
   if(preg_match('/^(?:BK-\d{4}-|PAY-\d{4}-|REQ-\d{4}-)?(\d+)$/i',$search,$m))$q->orWhere('id',(int)$m[1])->orWhere('treatment_request_id',(int)$m[1]);
  });
  $isBookings=$request->route('page')==='bookings';
  if($status=$filters['status']??null) {
   if($isBookings)$rows->where('status',$status);
   else $rows->where('deposit_status',$status==='Pending Payment'?'Pending':$status);
  }
  $date=$isBookings?'created_at':'payment_verified_at';
  if($filters['from']??null)$rows->whereDate($date,'>=',$filters['from']);
  if($filters['to']??null)$rows->whereDate($date,'<=',$filters['to']);
  $real=(clone $payments)->where('payment_is_simulated',false)->where('is_test',false);
  $bookings=$rows->latest()->paginate((int)($filters['size']??5));if(!$paymentReviewReady)foreach($bookings as $booking)$booking->setRelation('paymentSubmissions',collect());
  $transactionData=[];
  if(!$isBookings && $paymentReviewReady){
   $transactionBookings=Booking::where(fn($q)=>$q->paymentEligible()->orWhereHas('paymentSubmissions'));
   if($mode==='real')$transactionBookings->where('is_test',false);elseif($mode==='test')$transactionBookings->where('is_test',true);
   $transactionData=(new \App\Services\AdminPaymentTransactions)->pageData($filters,$transactionBookings);
  }
  return array_merge($transactionData,['realBookings'=>$bookings,'paymentReviewReady'=>$paymentReviewReady, 'paymentFilters'=>$filters,'paymentLocal'=>$local,
   'paymentTotals'=>[
    'received'=>(clone $real)->where('deposit_status','Paid')->sum('deposit_amount_satang') + ($paymentReviewReady ? \App\Models\PaymentSubmission::where('purpose','initial')->where('status','approved')->whereIn('booking_id',(clone $real)->where('deposit_phase','treatment')->select('bookings.id'))->sum('amount_satang') : 0),
    'paid'=>(clone $real)->where('deposit_status','Paid')->count(),
    'pending'=>(clone $real)->pendingPaymentEligible()->count(),
    'refunded'=>(clone $real)->sum('refunded_amount_satang'),
    'unknown'=>(clone $real)->where('deposit_status','Paid')->whereNull('deposit_amount_satang')->count(),
    'simulated'=>(clone $payments)->where('payment_is_simulated',true)->where('deposit_status','Paid')->sum('deposit_amount_satang'),
   ]]);
 }
 public function depositPolicy(Request $request, \App\Models\User $hospital) {
  abort_unless($request->user()?->isAdmin(),403);
  abort_unless($hospital->isHospital(),404);
  $data=$request->validate(['treatment_deposit_percentage'=>'required|integer|between:10,20']);
  $hospital->forceFill($data)->save();
  return back()->with('status','Hospital deposit percentage updated. Deposits already started keep their agreed percentage.');
 }
 public function updateBooking(Request $request,Booking $booking) {
  abort_unless($request->user()->isAdmin(),403);
  $data=$request->validate(['status'=>['required','in:Confirmed,Completed,Cancelled'],'appointment_date'=>['nullable','date_format:Y-m-d']]);
  return DB::transaction(function()use($booking,$data){
   $row=Booking::bookingEligible()->lockForUpdate()->findOrFail($booking->id);
   $allowed=['Pending Payment'=>['Cancelled'],'Awaiting Hospital Confirmation'=>['Confirmed','Cancelled'],'Confirmed'=>['Completed','Cancelled']];
   abort_unless(in_array($data['status'],$allowed[$row->status]??[],true),422,'Invalid booking status transition.');
   if(in_array($data['status'],['Confirmed','Completed'],true))abort_unless($row->deposit_status==='Paid' && $row->deposit_phase==='treatment' && $row->payment_verified_at && !$row->is_test && !$row->payment_is_simulated,422,'A verified deposit is required.');
   if(in_array($data['status'],['Confirmed','Completed'],true)){
    $payment=$row->paymentSubmissions()->where('purpose','treatment')->where('status','approved')->where('amount_satang',$row->deposit_amount_satang)->first();
    abort_unless($payment,422,'An approved Booking Deposit transaction is required.');
    (new \App\Services\PaymentLedger)->validateTreatmentPayment($row,$payment);
   }
   if($data['status']==='Confirmed'){
    if(empty($data['appointment_date']))throw \Illuminate\Validation\ValidationException::withMessages(['appointment_date'=>'Confirm the treatment date.']);
    $row->appointment_date=$data['appointment_date'];
   }
   $row->status=$data['status'];$row->save();
   return back()->with('status','Booking status updated. Payment status unchanged.');
  });
 }
 private function guard(Request $request): void {
  abort_unless($request->user()->isAdmin(),403);
  abort_unless(app()->environment(['local','testing']),404);
  $request->validate(['confirm_simulation'=>['accepted']]);
 }
 private function locked(Request $request,Booking $booking,callable $action) {
  $this->guard($request);
  return DB::transaction(function()use($request,$booking,$action){
   $item=TreatmentRequest::whereKey($booking->treatment_request_id)->lockForUpdate()->firstOrFail();
   $row=Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
   abort_unless(Booking::pendingPaymentEligible()->whereKey($row->id)->exists() && $row->status==='Pending Payment' && $row->deposit_status==='Pending' && !$row->payment_verified_at
    && !$item->payment_confirmed_at && $item->status!=='cancelled' && $item->selected_hospital_id===$row->hospital_id,409,'This booking is not eligible for test verification.');
   $action($row,$item);
   return back()->with('status','Local test payment updated. Simulation only; no real funds received.');
  });
 }
 public function markTest(Request $request,Booking $booking) {
  return $this->locked($request,$booking,function($row)use($request){
   abort_if($row->is_test,409,'Already a test booking.');
   $due=(new DepositPayment)->summary($row)['deposit_satang'];
   $row->update(['is_test'=>true,'test_marked_at'=>now(),'test_marked_by'=>$request->user()->id,'deposit_amount_satang'=>$due]);
  });
 }
 public function verify(Request $request,Booking $booking) {
  return $this->locked($request,$booking,function($row,$item)use($request){
   abort_unless($row->is_test,409,'Only explicitly marked test bookings can be simulated.');
   abort_unless($row->deposit_amount_satang!==null,409,'Deposit amount is not recorded.');
   $row->update(['deposit_status'=>'Paid','status'=>'Awaiting Hospital Confirmation','payment_is_simulated'=>true,'payment_verified_at'=>now(),'payment_verified_by'=>$request->user()->id]);
   $item->update(['payment_confirmed_at'=>now(),'status'=>'awaiting_hospital_confirmation']);
  });
 }
}
