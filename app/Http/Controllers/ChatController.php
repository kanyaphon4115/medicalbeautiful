<?php
namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\TreatmentRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\{DB,Gate};

class ChatController extends Controller
{
    private function supportAccess(Request $request):void
    {
        if(Gate::denies('accessSupport',ChatMessage::class)){
            app(\App\Services\ChatAccessAudit::class)->record($request,'support','denied_access',permitted:false);
            abort(403,'Customer Support requires an approved initial deposit and an activated account. Hospital accounts cannot access these conversations.');
        }
    }

    private function identity(Request $request): void
    {
        $expected = $request->header('X-Chat-User-Id');
        abort_if($expected !== null && $expected !== (string) $request->user()->id, 409,
            'Your signed-in account changed in another tab. Open this page again. Use separate browser profiles for different accounts.');
    }

    public function session(Request $request)
    {
        return response()->json(['csrf_token' => $request->session()->token(), 'user_id' => $request->user()->id])
            ->header('Cache-Control', 'private, no-store')->header('Vary', 'Cookie');
    }

    public function access(Request $request,\App\Services\BookingChatAccess $access)
    {
        $this->identity($request);
        $items=TreatmentRequest::where('user_id',$request->user()->id)->with(['booking.hospital','booking.quotation'])->get();
        $rooms=$items->map(function($item)use($access){
            $progress=$item->booking?$access->progress($item->booking):['step4_approved'=>false,'step9_approved'=>false,'account_activated'=>false,'booking_confirmed'=>false,'unlocked'=>false,'deposit_url'=>null];
            return ['id'=>$item->id,'label'=>$item->procedure_label.' - '.($item->selected_hospital_name??'Hospital pending'),'hospital_name'=>$item->selected_hospital_name,...$progress];
        });
        return response()->json(['user_id'=>$request->user()->id,'support_available'=>$access->supportAvailable($request->user()),'hospital_requests'=>$rooms])
            ->header('Cache-Control','private, no-store')->header('Vary','Cookie');
    }

    public function index(Request $request)
    {
        $this->identity($request);
        $channel = $request->query('channel', 'support');
        abort_unless(in_array($channel, ['support', 'hospital'], true), 422);
        if($channel==='support'){
            $this->supportAccess($request);
            abort_if($request->filled('treatment_request_id'),422,'Support conversations cannot use a hospital request ID.');
        }
        $item = $channel === 'hospital' ? $this->hospitalRequest($request, (int) $request->query('treatment_request_id')) : null;
        $owner = $item ? $item->user_id : $this->conversationOwner($request, $channel);
        $messageQuery = ChatMessage::where('user_id', $owner)->where('channel', $channel)
            ->where('treatment_request_id', $item?->id)->latest('id');
        if(!$request->user()->isChatSupport())$messageQuery->limit(50);
        $messages = $messageQuery->get()->reverse()->values()
            ->map(fn ($message) => $this->messageData($message));
        $eligible = TreatmentRequest::where('user_id', $request->user()->id)->get()
            ->filter(fn ($r) => $r->canChatWithHospital() && (!$r->booking || app(\App\Services\BookingChatAccess::class)->unlocked($r->booking)))
            ->map(fn ($r) => ['id' => $r->id, 'label' => $r->procedure.' - '.$r->selected_hospital_name, 'hospital_name'=>$r->booking?->hospital?->name ?? $r->selected_hospital_name])->values();
        $conversations = [];
        if ($request->user()->isChatSupport() && $channel === 'support') {
            $ids = ChatMessage::where('channel', 'support')->whereNull('treatment_request_id')
                ->select('user_id')->distinct()->pluck('user_id');
            $unread = DB::table('chat_messages as messages')
                ->leftJoin('chat_reads as reads', function ($join) use ($request) {
                    $join->on('reads.customer_id', '=', 'messages.user_id')
                        ->where('reads.reader_id', '=', $request->user()->id);
                })
                ->where('messages.channel', 'support')->whereNull('messages.treatment_request_id')
                ->where('messages.sender_type', 'customer')
                ->whereRaw('messages.id > COALESCE(reads.last_message_id, 0)')
                ->groupBy('messages.user_id')->selectRaw('messages.user_id, COUNT(*) as total')
                ->pluck('total', 'user_id');
            $latestIds=ChatMessage::where('channel','support')->whereNull('treatment_request_id')->selectRaw('MAX(id)')->groupBy('user_id');
            $latest=ChatMessage::whereIn('id',$latestIds)->get()->keyBy('user_id');
            $conversations = User::whereIn('id', $ids)->get(['id', 'name', 'role', 'email_verified_at'])
                ->map(function($user)use($latest,$unread){
                    $last=$latest->get($user->id);
                    return ['id'=>$user->id,'label'=>$user->name.' (#'.$user->id.')','customer_name'=>$user->name,
                        'latest_message'=>$last?->body,'last_message_time'=>$last?->created_at?->toIso8601String(),
                        'last_message_id'=>$last?->id,'status'=>app(\App\Services\BookingChatAccess::class)->supportAvailable($user)?'Active':'Read Only',
                        'unread_count'=>(int)($unread[$user->id]??0)];
                })->sortByDesc('last_message_id')->values();
        }
        app(\App\Services\ChatAccessAudit::class)->record($request,$channel,$channel==='support'&&!$request->filled('customer_id')?'list_conversations':'view_conversation',
            $item?->user_id ?? ($request->filled('customer_id')?$owner:null),$item?->id,$item?->booking?->id);
        return response()->json(['can_send'=>$channel==='support'?Gate::allows('sendSupport',[ChatMessage::class,$owner]):Gate::allows('sendHospitalRequest',[ChatMessage::class,$item]),'messages' => $messages, 'eligible_requests' => $eligible,
            'conversations' => $conversations, 'user_id' => $request->user()->id])
            ->header('Cache-Control', 'private, no-store')->header('Vary', 'Cookie');
    }

    public function store(Request $request)
    {
        $this->identity($request);
        if($request->input('channel')==='support')$this->supportAccess($request);
        abort_if(count($request->allFiles())>0,422,'Chat attachments are not supported.');
        $data = $request->validate([
            'channel' => ['required', Rule::in(['support', 'hospital'])],
            'body' => ['required', 'string', 'max:2000'],
            'treatment_request_id' => ['nullable', 'prohibited_unless:channel,hospital', 'integer'],
            'attachments'=>['prohibited'],'attachment'=>['prohibited'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $body = trim($data['body']);
        abort_if($body === '', 422, 'Please enter a message.');
        $item = $data['channel'] === 'hospital' ? $this->hospitalRequest($request, (int) ($data['treatment_request_id'] ?? 0),true) : null;
        $owner = $item ? $item->user_id : $this->conversationOwner($request, $data['channel']);
        if($data['channel']==='support')Gate::authorize('sendSupport',[ChatMessage::class,$owner]);
        $reply = $owner !== $request->user()->id;
        if ($reply && $data['channel']==='support') {
            abort_unless(ChatMessage::where('user_id', $owner)->where('channel', 'support')
                ->whereNull('treatment_request_id')->exists(), 404);
        }
        $message=DB::transaction(function()use($request,$data,$body,$owner,$item){
            if($item){
                $item=TreatmentRequest::whereKey($item->id)->lockForUpdate()->firstOrFail();
                if($item->booking)\App\Models\Booking::whereKey($item->booking->id)->lockForUpdate()->firstOrFail();
                $item->unsetRelation('booking');
                Gate::authorize('sendHospitalRequest',[ChatMessage::class,$item]);
                $owner=$item->user_id;
            }
            $message=ChatMessage::create(['user_id'=>$owner,'treatment_request_id'=>$item?->id,'channel'=>$data['channel'],
                'sender_type'=>$data['channel']==='hospital'?($request->user()->isHospital()?'hospital':'customer'):($request->user()->isChatSupport()?'support':'customer'),'body'=>$body]);
            app(\App\Services\ChatAccessAudit::class)->record($request,$data['channel'],'send_message',$owner,$item?->id,$item?->booking?->id);
            return $message;
        });
        return response()->json(['id' => $message->id, 'message' => $this->messageData($message)], 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request)
    {
        $this->identity($request);
        abort_unless($request->user()->isChatSupport(), 403);
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'min:1'],
            'last_message_id' => ['required', 'integer', 'min:1'],
        ]);
        abort_unless(ChatMessage::where('user_id', $data['customer_id'])->where('channel', 'support')
            ->whereNull('treatment_request_id')->whereKey($data['last_message_id'])->exists(), 404);
        DB::table('chat_reads')->insertOrIgnore([
            'reader_id' => $request->user()->id, 'customer_id' => $data['customer_id'], 'last_message_id' => 0,
        ]);
        DB::table('chat_reads')->where('reader_id', $request->user()->id)
            ->where('customer_id', $data['customer_id'])->where('last_message_id', '<', $data['last_message_id'])
            ->update(['last_message_id' => $data['last_message_id']]);

        app(\App\Services\ChatAccessAudit::class)->record($request,'support','mark_read',$data['customer_id']);
        return response()->json(['saved' => true])->header('Cache-Control', 'private, no-store');
    }

    private function conversationOwner(Request $request, string $channel): int
    {
        $this->supportAccess($request);
        $target = $request->input('customer_id');
        if ($target === null || $target === '') return $request->user()->id;
        abort_unless($channel==='support',403);
        Gate::authorize('viewSupport',[ChatMessage::class,(int)$target]);
        abort_unless(ctype_digit((string) $target) && (int) $target > 0, 422);
        return User::findOrFail((int) $target)->id;
    }

    private function messageData(ChatMessage $message): array
    {
        return ['id' => $message->id, 'body' => $message->body, 'sender_type' => $message->sender_type,
            'sent_at' => $message->created_at->toIso8601String()];
    }

    private function hospitalRequest(Request $request, int $id,bool $write=false): TreatmentRequest
    {
        $query=TreatmentRequest::query();
        if(!$request->user()->isChatSupport()&&!$request->user()->isHospital())$query->where('user_id',$request->user()->id);
        $item=$query->findOrFail($id);
        if($request->user()->isHospital())abort_unless($item->selected_hospital_id===$request->user()->id,404);
        Gate::authorize($write?'sendHospitalRequest':'viewHospitalRequest',[ChatMessage::class,$item]);
        return $item;
    }
}
