<?php
namespace App\Jobs;

use App\Models\TreatmentRequest;
use App\Notifications\GuestJourneyCode;
use Illuminate\Contracts\Queue\{ShouldQueue,ShouldBeEncrypted};
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{Cache,Notification,URL};

class SendGuestActivationCode implements ShouldQueue,ShouldBeEncrypted {
 use Queueable;
 public int $tries=3;
 public int $timeout=60;
 public function __construct(public int $requestId,private string $code,public string $generation){}
 public function backoff():array{return [30,60,120];}
 public function handle():void {
  $item=TreatmentRequest::with('booking')->find($this->requestId);if(!$item)return;
  $key='real-guest-code:'.$item->reference;
  Cache::lock($key.':delivery',120)->block(5,function()use($item,$key){
   $record=Cache::get($key);$b=$item->booking;
   if(!$record||($record['generation']??null)!==$this->generation||!empty($record['delivered_at'])||$record['expires']<=now()->timestamp)return;
   if(!$b||$b->deposit_status!=='Paid'||$b->is_test||$b->payment_is_simulated||!$b->payment_verified_at||!$item->payment_confirmed_at||$item->user_id)return;
   if(($record['booking']??null)!==$b->id||($record['email']??null)!==$item->email)return;
   if(!app()->runningUnitTests()&&config('mail.default')!=='smtp')throw new \RuntimeException('SMTP delivery is not configured.');
   $url=URL::temporarySignedRoute('guest.requests.verification-entry',now()->addDays(7),['reference'=>$item->reference]).'#guest-verification';
   try{Notification::route('mail',$item->email)->notify(new GuestJourneyCode($this->code,true,$url));}
   catch(\Throwable $e){throw new \RuntimeException('Payment confirmation email delivery failed. Check mail configuration or retry.');}
   // Do not overwrite a newer resend or restore a code consumed during delivery.
   Cache::lock($key.':record',10)->block(3,function()use($key){
    $current=Cache::get($key);
    if($current&&($current['generation']??null)===$this->generation){$current['delivered_at']=now()->timestamp;Cache::put($key,$current,\Carbon\Carbon::createFromTimestamp($current['expires']));}
   });
  });
 }
}
