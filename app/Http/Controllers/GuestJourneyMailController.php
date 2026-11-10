<?php

namespace App\Http\Controllers;

use App\Notifications\GuestJourneyCode;
use App\Notifications\GuestRequestAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Real email access for the existing prototype, NOT a payment/account API.
// Encrypted, expiring staging data; never writes bookings, users or real payments.
class GuestJourneyMailController extends Controller
{
    public function store(Request $request)
    {
        $request->merge(['email'=>Str::lower(trim((string)$request->input('email')))]);
        $data=$request->validate([
            'name'=>'required|string|max:80', 'email'=>'required|email|max:254',
            'line_id'=>'nullable|string|max:100', 'phone_number'=>['nullable','string','regex:/^\+[1-9][0-9]{6,14}$/'],
            'age'=>'nullable|integer|between:18,120', 'gender'=>['nullable',Rule::in(['Female','Male','Non-binary','Other','Prefer not to say'])],
            'procedures'=>'required|array|min:1|max:11', 'procedures.*'=>['required','distinct',Rule::in(TreatmentRequestController::PROCEDURES)],
            'goals'=>'nullable|string|max:1000', 'medical_information'=>'nullable|string|max:3000',
            'preferred_surgery_month'=>'nullable|required_with:preferred_surgery_year|integer|between:1,12',
            'preferred_surgery_year'=>'nullable|required_with:preferred_surgery_month|integer|min:'.now()->year.'|max:'.(now()->year+10),
            'hospitals'=>'required|array|min:1|max:6', 'hospitals.*'=>['required','distinct',Rule::in(['aesthetic-hospital-bangkok','beauty-plus-hospital','the-signature-clinic','prime-aesthetic-center','grace-aesthetic-hospital','wellness-clinic'])],
            'consent_to_share'=>'accepted',
        ]);
        if (!empty($data['preferred_surgery_month']) && sprintf('%04d-%02d',$data['preferred_surgery_year'],$data['preferred_surgery_month'])<now()->format('Y-m')) {
            throw ValidationException::withMessages(['preferred_surgery_month'=>'Select the current month or a future month.']);
        }
        $key='guest-request-send:'.hash('sha256',$data['email']);
        if (RateLimiter::tooManyAttempts($key,3)) abort(429,'Please wait before submitting another request.');
        RateLimiter::hit($key,3600);
        $id=(string)Str::uuid();
        $period=!empty($data['preferred_surgery_month'])?sprintf('%04d-%02d',$data['preferred_surgery_year'],$data['preferred_surgery_month']):null;
        $record=['token'=>$id,'id'=>'GUEST-'.strtoupper(Str::random(10)),'name'=>$data['name'],'email'=>$data['email'],
            'procedures'=>$data['procedures'],'procedure'=>implode(', ',$data['procedures']),'date'=>$period,
            'description'=>$data['goals']??'','hospitals'=>$data['hospitals'],'contact'=>$data,
            'expires'=>now()->addDays(7)->timestamp,'created'=>now()->getTimestampMs(),
            'accessUntil'=>0,'quotes'=>[],'booking'=>null,'verified'=>false,'owner'=>null,'messages'=>[],'photos'=>[],'emailRequest'=>true];
        $this->put($id,$record);
        try {
            $url=URL::temporarySignedRoute('guest-journey.mail.access',now()->addDays(7),['guest'=>$id]);
            $this->send($data['email'],new GuestRequestAccess($url,$record['id'],$period?\Carbon\Carbon::createFromFormat('!Y-m',$period)->format('F Y'):'Not specified'));
        } catch (\Throwable $error) {
            Cache::forget('guest-journey:'.$id);
            return response()->json(['message'=>'Email could not be sent. Please try again later.'],503);
        }
        return response()->json(['token'=>$id,'reference'=>$record['id'],'message'=>'Your access link has been sent. Check your inbox and spam folder.'],201)->header('Cache-Control','no-store');
    }

    public function access(Request $request,string $guest)
    {
        $record=$this->record($guest);
        $request->session()->regenerate();
        $request->session()->put('guest-journey.'.$guest,$record['expires']);
        return redirect(route('home').'#journey/email/'.$guest)->header('Referrer-Policy','no-referrer')->header('Cache-Control','no-store');
    }

    public function show(Request $request,string $guest)
    {
        $record=$this->authorized($request,$guest);
        unset($record['contact'],$record['expires']);
        $record['accessUntil']=min(now()->addHours(12)->getTimestampMs(),$request->session()->get('guest-journey.'.$guest)*1000);
        return response()->json($record)->header('Cache-Control','private, no-store');
    }

    public function code(Request $request,string $guest)
    {
        $record=$this->authorized($request,$guest);
        $key='guest-code-send:'.hash('sha256',$record['email']);
        if (RateLimiter::tooManyAttempts($key,1)) abort(429,'Please wait 30 seconds before resending the code.');
        if (RateLimiter::tooManyAttempts($key.':hour',10)) abort(429,'Too many code requests. Please try again later.');
        RateLimiter::hit($key,30); RateLimiter::hit($key.':hour',3600);
        $code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        try { $this->send($record['email'],new GuestJourneyCode($code)); }
        catch (\Throwable $error) { return response()->json(['message'=>'Email could not be sent. Please try again later.'],503); }
        Cache::put('guest-journey-code:'.$guest,['hash'=>Hash::make($code),'expires'=>now()->addMinutes(5)->timestamp],now()->addMinutes(5));
        return response()->json(['message'=>'A 6-digit code has been sent to your email. It expires in 5 minutes.'])->header('Cache-Control','no-store');
    }

    public function verify(Request $request,string $guest)
    {
        $this->authorized($request,$guest);
        $data=$request->validate(['code'=>'required|digits:6']);
        $key='guest-journey-code:'.$guest;
        return Cache::lock($key.':lock',10)->block(3,function() use($key,$data,$guest) {
            $attempts=$key.':attempts';
            if (RateLimiter::tooManyAttempts($attempts,5)) abort(429,'Too many attempts. Please try again in 5 minutes.');
            RateLimiter::hit($attempts,300);
            $code=Cache::get($key);
            if (!$code || $code['expires']<=now()->timestamp || !Hash::check($data['code'],$code['hash'])) {
                throw ValidationException::withMessages(['code'=>'The code is incorrect or expired.']);
            }
            Cache::forget($key);
            return response()->json(['verified'=>true,'message'=>'Email verified for this prototype session. Real payment and account activation remain unavailable.'])->header('Cache-Control','no-store');
        });
    }

    private function record(string $id): array
    {
        $encrypted=Cache::get('guest-journey:'.$id);
        abort_unless($encrypted,404,'This request access link has expired.');
        $record=json_decode(Crypt::decryptString($encrypted),true);
        abort_unless($record['expires']>now()->timestamp,404);
        return $record;
    }
    private function put(string $id,array $record): void
    {
        Cache::put('guest-journey:'.$id,Crypt::encryptString(json_encode($record)),now()->addDays(7));
    }
    private function authorized(Request $request,string $id): array
    {
        abort_unless($request->session()->get('guest-journey.'.$id,0)>now()->timestamp,403,'Open the private access link in your email first.');
        return $this->record($id);
    }
    private function send(string $email,$notification): void
    {
        if (!app()->runningUnitTests() && in_array(config('mail.default'),['log','array'],true)) throw new \RuntimeException('Mail delivery is not configured.');
        Notification::route('mail',$email)->notify($notification);
    }
}
