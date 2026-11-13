<?php
namespace Tests\Concerns;
use App\Models\{User,Booking,TreatmentRequest,Quotation};
use Illuminate\Support\Str;
trait HasApprovedChatBooking {
 protected function approvedChatBooking(User $customer,bool $confirmed=true,?TreatmentRequest $item=null):Booking {
  $customer->forceFill(['email_verified_at'=>now()])->save();$hospital=User::factory()->create(['role'=>'hospital']);
  $item??=TreatmentRequest::create(['reference'=>(string)Str::uuid(),'user_id'=>$customer->id,'name'=>$customer->name,'email'=>$customer->email,'procedure'=>'Eye Surgery','status'=>'pending','consented_at'=>now(),'consent_text'=>'Test consent']);
  $item->update(['selected_hospital_id'=>$hospital->id,'selected_hospital_name'=>$hospital->name,'hospital_approved_at'=>now(),'payment_confirmed_at'=>now(),'status'=>$confirmed?'confirmed':'awaiting_hospital_confirmation']);
  $quote=Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>650000,'details'=>'Test quotation','valid_until'=>today()->addMonth()]);
  $booking=Booking::create(['treatment_request_id'=>$item->id,'quotation_id'=>$quote->id,'hospital_id'=>$hospital->id,'user_id'=>$customer->id,'deposit_phase'=>$confirmed?'treatment':'initial','deposit_percentage'=>10,'deposit_status'=>'Paid','deposit_amount_satang'=>$confirmed?6000000:500000,'payment_verified_at'=>now(),'status'=>$confirmed?'Confirmed':'Awaiting Hospital Confirmation']);
  $booking->paymentSubmissions()->create(['purpose'=>'initial','status'=>'approved','amount_satang'=>500000,'method'=>'Bank Transfer','submitted_at'=>now(),'reviewed_at'=>now()]);
  if($confirmed)$booking->paymentSubmissions()->create(['purpose'=>'treatment','status'=>'approved','amount_satang'=>6000000,'method'=>'Bank Transfer','submitted_at'=>now(),'reviewed_at'=>now()]);
  return $booking;
 }
}
