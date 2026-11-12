<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ChatAccessAudit {
 public function record(Request $request,string $type,string $action,?int $customerId=null,?int $requestId=null,?int $bookingId=null,bool $permitted=true):void {
  $actor=$request->user();if(!$actor||(!$actor->isChatSupport()&&!$actor->isHospital()))return;
  // Minimal access metadata only: never record message bodies, attachments, email addresses, tokens or IP addresses.
  DB::table('chat_access_logs')->insert(['actor_id'=>$actor->id,'actor_role'=>$actor->role,'conversation_type'=>$type,'action'=>$action,'permitted'=>$permitted,
   'purpose'=>$actor->isAdmin()?'administration':($actor->isHospital()?'hospital_service':'customer_service'),
   'customer_id'=>$customerId,'treatment_request_id'=>$requestId,'booking_id'=>$bookingId,'created_at'=>now()]);
 }
}
