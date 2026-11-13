<?php
namespace App\Http\Controllers;

use App\Models\{Booking,ChatMessage,TreatmentRequest};
use App\Services\BookingChatAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Gate};

class BookingChatController extends Controller
{
    private function identity(Request $request):void {
        Gate::authorize('listHospital',\App\Models\ChatMessage::class);
        $expected=$request->header('X-Chat-User-Id');
        abort_if($expected!==null && $expected!==(string)$request->user()->id,409,'Your signed-in account changed. Please reload this page.');
    }
    public function index(Request $request,BookingChatAccess $access){
        $this->identity($request);
        $query=Booking::bookingEligible()->with(['treatmentRequest','customer','hospital']);
        if($request->user()->isHospital())$query->where('hospital_id',$request->user()->id)->whereHas('treatmentRequest',fn($q)=>$q->whereColumn('selected_hospital_id','bookings.hospital_id'));
        $rooms=$query->latest()->get()->map(function($booking)use($access,$request){
            $item=$booking->treatmentRequest;$progress=$access->progress($booking);
            return ['id'=>$booking->id,'booking_reference'=>$booking->reference,'treatment_request_id'=>$item->id,
                'request_reference'=>sprintf('REQ-%s-%06d',$item->created_at->format('Y'),$item->id),
                'customer_name'=>$item->name,'customer_email'=>$item->email,'hospital_name'=>$booking->hospital?->name ?? 'Not recorded',
                'procedure'=>$item->procedure_label,'booking_status'=>$booking->status,'deposit_status'=>$booking->deposit_status,
                'hospital_selected'=>$item->selected_hospital_id===$booking->hospital_id,'hospital_approved'=>(bool)$item->hospital_approved_at,
                'payment_approved'=>$progress['step4_approved'],'account_active'=>$progress['account_activated'],'step4_approved'=>$progress['step4_approved'],'step9_approved'=>$progress['step9_approved'],'unlocked'=>$progress['unlocked'],'can_view'=>Gate::allows('viewHospital',[\App\Models\ChatMessage::class,$booking])];
        });
        app(\App\Services\ChatAccessAudit::class)->record($request,'hospital','list_conversations');
        return response()->json(['rooms'=>$rooms,'user_id'=>$request->user()->id,'role'=>$request->user()->isHospital()?'hospital':'support','can_send'=>$request->user()->isHospital()])
            ->header('Cache-Control','private, no-store')->header('Vary','Cookie');
    }
    public function messages(Request $request,Booking $booking,BookingChatAccess $access){
        $this->identity($request);$access->authorize($request->user(),$booking);
        Gate::authorize('viewHospital',[\App\Models\ChatMessage::class,$booking]);
        $messages=ChatMessage::where('user_id',$booking->user_id)->where('channel','hospital')->where('treatment_request_id',$booking->treatment_request_id)
            ->latest('id')->limit(50)->get()->reverse()->values()->map(fn($message)=>$this->messageData($message));
        app(\App\Services\ChatAccessAudit::class)->record($request,'hospital','view_conversation',$booking->user_id,$booking->treatment_request_id,$booking->id);
        return response()->json(['messages'=>$messages])->header('Cache-Control','private, no-store')->header('Vary','Cookie');
    }
    public function store(Request $request,Booking $booking,BookingChatAccess $access){
        $this->identity($request);$access->authorize($request->user(),$booking);
        abort_unless($request->user()->isHospital(),403,'Only the selected hospital can reply in this hospital conversation. Use Customer Support for support messages.');
        abort_if(count($request->allFiles())>0,422,'Chat attachments are not supported.');
        $data=$request->validate(['body'=>'required|string|max:2000','attachments'=>'prohibited','attachment'=>'prohibited']);$body=trim($data['body']);
        abort_if($body==='',422,'Please enter a message.');
        $message=DB::transaction(function()use($booking,$request,$access,$body){
            TreatmentRequest::whereKey($booking->treatment_request_id)->lockForUpdate()->firstOrFail();
            $row=Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();$access->authorize($request->user(),$row);
            Gate::authorize('sendHospital',[\App\Models\ChatMessage::class,$row]);
            $message=ChatMessage::create(['user_id'=>$row->user_id,'treatment_request_id'=>$row->treatment_request_id,'channel'=>'hospital',
                'sender_type'=>'hospital','body'=>$body]);
            app(\App\Services\ChatAccessAudit::class)->record($request,'hospital','send_message',$row->user_id,$row->treatment_request_id,$row->id);
            return $message;
        });
        return response()->json(['id'=>$message->id,'message'=>$this->messageData($message)],201)->header('Cache-Control','private, no-store');
    }
    private function messageData(ChatMessage $message):array {
        return ['id'=>$message->id,'body'=>$message->body,'sender_type'=>$message->sender_type,'sent_at'=>$message->created_at->toIso8601String()];
    }
}
