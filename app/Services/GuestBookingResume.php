<?php
namespace App\Services;

use App\Models\TreatmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache,Cookie};
use Illuminate\Support\Str;

class GuestBookingResume
{
    public const COOKIE = 'guest_booking_return';

    // The cookie is encrypted by Laravel and contains only a random capability.
    // The server keeps its hash and scope; a public request/booking ID grants nothing.
    public function remember(Request $request, string $reference): void
    {
        $token=$request->cookie(self::COOKIE);
        if(!is_string($token)||!preg_match('/^[a-zA-Z0-9]{64}$/',$token))$token=Str::random(64);
        $key='guest-return:'.hash('sha256',$token);$stored=Cache::get($key,[]);
        if(is_string($stored))$stored=[$stored=>now()->addDays(7)->timestamp];
        $stored=array_filter(is_array($stored)?$stored:[],fn($expiry)=>is_numeric($expiry)&&$expiry>now()->timestamp);
        $stored[$reference]=now()->addDays(7)->timestamp;
        Cache::put($key,array_slice($stored,-50,null,true),now()->addDays(7));
        Cookie::queue(cookie(self::COOKIE,$token,7*24*60,'/',null,$request->isSecure(),true,false,'lax'));
        $request->session()->put('real_guest_latest',$reference);
    }

    public function references(Request $request):array
    {
        $references=[];$token=$request->cookie(self::COOKIE);
        if(is_string($token)&&preg_match('/^[a-zA-Z0-9]{64}$/',$token)){
            $stored=Cache::get('guest-return:'.hash('sha256',$token),[]);
            if(is_string($stored))$stored=[$stored=>now()->addMinutes(config('session.lifetime'))->timestamp];
            if(is_array($stored))$references=$stored;
        }
        foreach($request->session()->get('real_guest',[]) as $reference=>$expiry)if(is_numeric($expiry)&&$expiry>now()->timestamp)$references[$reference]=$expiry;
        return array_filter($references,fn($expiry)=>is_numeric($expiry)&&$expiry>now()->timestamp);
    }
    public function reference(Request $request):?string
    {
        $all=$this->references($request);$latest=$request->session()->get('real_guest_latest');
        return isset($all[$latest])?$latest:array_key_last($all);
    }

    public function state(Request $request,TreatmentRequest $item): array
    {
        $booking=$item->booking;
        $verifiedPayment=$booking && $booking->deposit_status==='Paid' && !$booking->is_test && !$booking->payment_is_simulated
            && $booking->payment_verified_at && $item->payment_confirmed_at;
        $status=match($booking?->deposit_status){
            'Pending'=>'pending_payment','Pending Verification'=>'pending_verification',
            'Paid'=>$verifiedPayment?'paid':'pending_verification','Rejected'=>'rejected','Information Required'=>'information_required','Failed'=>'failed','Expired'=>'expired','Refunded'=>'refunded',
            default=>'not_started',
        };
        $grant=$request->session()->get('real_guest_verified.'.$item->reference);
        $verifiedEmail=$verifiedPayment && $grant && ($grant['expires']??0)>now()->timestamp
            && ($grant['booking']??null)===$booking->id && ($grant['email']??null)===$item->email;
        $step=$status==='paid'?($item->user_id?'dashboard':($verifiedEmail?'account':'verification')):'payment';
        return ['status'=>$status,'step'=>$step,'verified_payment'=>(bool)$verifiedPayment,'verified_email'=>(bool)$verifiedEmail,
            'anchor'=>match($step){'verification'=>'guest-verification','account'=>'guest-account',default=>'booking-deposit'}];
    }
}
