<?php
namespace App\Services;
use App\Models\TreatmentRequest;
use App\Jobs\SendGuestActivationCode;
use Illuminate\Support\Facades\{Cache,Hash,RateLimiter};
use Illuminate\Support\Str;
class GuestActivationCode {
 public function send(TreatmentRequest $item):void {
  $b=$item->booking;
  abort_unless($b&&$b->deposit_status==='Paid'&&!$b->is_test&&!$b->payment_is_simulated&&$b->payment_verified_at&&$item->payment_confirmed_at,422);
  $key='real-guest-code:'.$item->reference;
  Cache::lock($key.':lock',10)->block(3,function()use($key,$item,$b){
   abort_if(RateLimiter::tooManyAttempts($key.':send',1),429,'Please wait one minute before resending.');
   $code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
   if(!app()->runningUnitTests()&&config('mail.default')!=='smtp')throw new \RuntimeException('SMTP is not configured.');
   $generation=(string)Str::uuid();
   Cache::lock($key.':record',10)->block(3,function()use($key,$code,$generation,$b,$item){Cache::put($key,['hash'=>Hash::make($code),'generation'=>$generation,'booking'=>$b->id,'email'=>$item->email,'expires'=>now()->addMinutes(10)->timestamp],now()->addMinutes(10));});
   RateLimiter::hit($key.':send',60);RateLimiter::clear($key.':attempts');
   // Approval has committed before this call; deliver without requiring a queue worker.
   try{SendGuestActivationCode::dispatchSync($item->id,$code,$generation);}catch(\Throwable $e){RateLimiter::clear($key.':send');throw $e;}

  });
 }
}
