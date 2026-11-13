<?php
namespace App\Policies;
use App\Models\{User,Booking,ChatMessage,TreatmentRequest};
use App\Services\BookingChatAccess;
class ChatMessagePolicy {
 public function accessSupport(User $user):bool{return $user->isChatSupport() || ($user->role===User::ROLE_USER && app(BookingChatAccess::class)->supportAvailable($user));}
 public function viewSupport(User $user,int $customerId):bool{return $this->accessSupport($user)&&($user->isChatSupport()||$user->id===$customerId);}
 public function sendSupport(User $user,int $customerId):bool {
  $customer=User::find($customerId);
  return $customer && $this->viewSupport($user,$customerId) && app(BookingChatAccess::class)->supportAvailable($customer);
 }
 public function listHospital(User $user):bool{return $user->isChatSupport()||$user->isHospital();}
 public function viewHospital(User $user,Booking $booking):bool {
  return ($user->isChatSupport() || $this->hospitalParticipant($user,$booking->treatmentRequest)) && app(BookingChatAccess::class)->unlocked($booking);
 }
 public function sendHospital(User $user,Booking $booking):bool {
  return !$user->isChatSupport() && $this->hospitalParticipant($user,$booking->treatmentRequest) && app(BookingChatAccess::class)->unlocked($booking);
 }
 private function hospitalParticipant(User $user,?TreatmentRequest $item):bool {
  return $item && $item->user_id && (($user->role===User::ROLE_USER&&$user->id===$item->user_id)||($user->isHospital()&&$user->id===$item->selected_hospital_id));
 }
 public function viewHospitalRequest(User $user,TreatmentRequest $item):bool {
  return $item->booking && ($user->isChatSupport()||$this->hospitalParticipant($user,$item)) && app(BookingChatAccess::class)->unlocked($item->booking);
 }
 public function sendHospitalRequest(User $user,TreatmentRequest $item):bool {
  return !$user->isChatSupport() && $this->viewHospitalRequest($user,$item);
 }
 public function view(User $user,ChatMessage $message):bool {
  if($message->channel==='support')return $message->treatment_request_id===null&&$this->viewSupport($user,$message->user_id);
  if($message->channel!=='hospital')return false;
  $item=TreatmentRequest::find($message->treatment_request_id);
  return $item && $item->user_id===$message->user_id && $this->viewHospitalRequest($user,$item);
 }
 public function viewAttachment(User $user,ChatMessage $message):bool{return $this->view($user,$message);}
 public function receiveNotification(User $user,ChatMessage $message):bool{return $this->view($user,$message);}
 public function update(User $user,ChatMessage $message):bool{return false;}
 public function delete(User $user,ChatMessage $message):bool{return false;}
}
