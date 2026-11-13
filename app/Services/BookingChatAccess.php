<?php
namespace App\Services;
use App\Models\{Booking,User};
class BookingChatAccess {
 public function authorize(User $user,Booking $booking):void {
  abort_unless($user->isChatSupport()||$user->isHospital(),403);
  abort_unless($user->isChatSupport()||$booking->hospital_id===$user->id,404);
 }
 private function approvedAmount(Booking $booking,string $purpose):int {
  return (int)$booking->paymentSubmissions()->where('purpose',$purpose)->whereIn('status',['approved','Paid','paid'])->whereNotNull('reviewed_at')->sum('amount_satang');
 }
 public function initialApproved(Booking $booking):bool {
  $item=$booking->treatmentRequest;
  return $item && $item->payment_confirmed_at && !$booking->is_test && !$booking->payment_is_simulated
   && ($booking->deposit_phase==='treatment'||($booking->deposit_status==='Paid'&&$booking->payment_verified_at))
   && $this->approvedAmount($booking,'initial')>0;
 }
 public function accountActivated(Booking $booking):bool {
  return $booking->user_id && $booking->user_id===$booking->treatmentRequest?->user_id
   && User::whereKey($booking->user_id)->where('role',User::ROLE_USER)->whereNotNull('email_verified_at')->exists();
 }
 public function supportAvailable(User $customer):bool {
  if($customer->isChatSupport())return true;
  if($customer->role!==User::ROLE_USER||!User::whereKey($customer->id)->whereNotNull('email_verified_at')->exists())return false;
  return Booking::where('user_id',$customer->id)->with('treatmentRequest')->get()->contains(fn($booking)=>$this->accountActivated($booking)&&$this->initialApproved($booking));
 }
 public function progress(Booking $booking):array {
  $item=$booking->treatmentRequest;$initial=$this->initialApproved($booking);$activated=$this->accountActivated($booking);
  $initialAmount=$this->approvedAmount($booking,'initial');
  $percentage=(int)($booking->deposit_percentage??$booking->hospital?->treatment_deposit_percentage??10);
  $total=(int)str_replace('.','',$booking->quotation?->amount??'0.00');
  $due=max(0,intdiv($total*$percentage+50,100)-$initialAmount);
  $stepNine=$percentage>=10 && $percentage<=20 && $due>0 && $booking->deposit_amount_satang===$due && $booking->deposit_phase==='treatment' && $booking->deposit_status==='Paid' && $booking->payment_verified_at
   && !$booking->is_test && !$booking->payment_is_simulated && $this->approvedAmount($booking,'treatment')>=$due;
  $confirmed=$booking->status==='Confirmed';
  $valid=$item && $item->status!=='cancelled' && filled($item->procedure) && filled($item->selected_hospital_name) && $item->hospital_approved_at
   && $item->selected_hospital_id===$booking->hospital_id && $booking->hospital?->isHospital()
   && $booking->quotation?->hospital_id===$booking->hospital_id && $booking->quotation?->treatment_request_id===$item->id;
  return ['step4_approved'=>(bool)$initial,'step9_approved'=>(bool)$stepNine,'account_activated'=>(bool)$activated,
   'booking_confirmed'=>$confirmed,'unlocked'=>(bool)($valid&&$initial&&$activated&&$stepNine&&$confirmed),
   'deposit_url'=>$initial&&$activated?route('requests.index').'#booking-deposit-'.$booking->id:null];
 }
 public function unlocked(Booking $booking):bool {return $this->progress($booking)['unlocked'];}
}
