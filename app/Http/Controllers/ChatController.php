<?php
namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\TreatmentRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
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

    public function index(Request $request)
    {
        $this->identity($request);
        $channel = $request->query('channel', 'support');
        abort_unless(in_array($channel, ['support', 'hospital'], true), 422);
        $item = $channel === 'hospital' ? $this->hospitalRequest($request, (int) $request->query('treatment_request_id')) : null;
        $owner = $this->conversationOwner($request, $channel);
        $messages = ChatMessage::where('user_id', $owner)->where('channel', $channel)
            ->where('treatment_request_id', $item?->id)->latest('id')->limit(50)->get()->reverse()->values()
            ->map(fn ($message) => $this->messageData($message));
        $eligible = TreatmentRequest::where('user_id', $request->user()->id)->get()
            ->filter(fn ($r) => $r->canChatWithHospital())
            ->map(fn ($r) => ['id' => $r->id, 'label' => $r->procedure.' - '.$r->selected_hospital_name])->values();
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
            $conversations = User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($user) => ['id' => $user->id, 'label' => $user->name.' (#'.$user->id.')',
                    'unread_count' => (int) ($unread[$user->id] ?? 0)])->values();
        }
        return response()->json(['messages' => $messages, 'eligible_requests' => $eligible,
            'conversations' => $conversations, 'user_id' => $request->user()->id])
            ->header('Cache-Control', 'private, no-store')->header('Vary', 'Cookie');
    }

    public function store(Request $request)
    {
        $this->identity($request);
        $data = $request->validate([
            'channel' => ['required', Rule::in(['support', 'hospital'])],
            'body' => ['required', 'string', 'max:2000'],
            'treatment_request_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $body = trim($data['body']);
        abort_if($body === '', 422, 'Please enter a message.');
        $item = $data['channel'] === 'hospital' ? $this->hospitalRequest($request, (int) ($data['treatment_request_id'] ?? 0)) : null;
        $owner = $this->conversationOwner($request, $data['channel']);
        $reply = $owner !== $request->user()->id;
        if ($reply) {
            abort_unless(ChatMessage::where('user_id', $owner)->where('channel', 'support')
                ->whereNull('treatment_request_id')->exists(), 404);
        }
        $message = ChatMessage::create(['user_id' => $owner, 'treatment_request_id' => $item?->id,
            'channel' => $data['channel'], 'sender_type' => $reply ? 'support' : 'customer', 'body' => $body]);
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

        return response()->json(['saved' => true])->header('Cache-Control', 'private, no-store');
    }

    private function conversationOwner(Request $request, string $channel): int
    {
        $target = $request->input('customer_id');
        if ($target === null || $target === '') return $request->user()->id;
        abort_unless($channel === 'support' && $request->user()->isChatSupport(), 403);
        abort_unless(ctype_digit((string) $target) && (int) $target > 0, 422);
        return User::findOrFail((int) $target)->id;
    }

    private function messageData(ChatMessage $message): array
    {
        return ['id' => $message->id, 'body' => $message->body, 'sender_type' => $message->sender_type,
            'sent_at' => $message->created_at->toIso8601String()];
    }

    private function hospitalRequest(Request $request, int $id): TreatmentRequest
    {
        $item = TreatmentRequest::where('user_id', $request->user()->id)->findOrFail($id);
        abort_unless($item->canChatWithHospital(), 403,
            'Hospital chat unlocks after procedure selection, hospital approval, and confirmed payment.');
        return $item;
    }
}
