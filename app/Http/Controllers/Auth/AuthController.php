<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): RedirectResponse
    {
        abort(403, 'Public registration is unavailable. Submit a request and complete the verified customer journey first.');
    }

    public function login(Request $request): RedirectResponse
    {
        $this->normalizeEmail($request);
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
        ]);

        $key = 'login:'.hash('sha256', $credentials['email'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        if (strlen($credentials['password']) > 72 || ! Auth::attempt($credentials)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => 'The email address or password is incorrect.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $reference=$request->session()->get('real_guest_link');
        if($reference){
            $grant=$request->session()->get('real_guest_verified.'.$reference);
            if($grant && $grant['expires']>now()->timestamp && $grant['email']===$request->user()->email){
                $linked=\Illuminate\Support\Facades\DB::transaction(function()use($reference,$grant,$request){
                    $item=\App\Models\TreatmentRequest::where('reference',$reference)->where('email',$request->user()->email)->lockForUpdate()->first();
                    if(!$item || ($item->user_id && $item->user_id!==$request->user()->id))return false;
                    $booking=$item->booking()->whereKey($grant['booking'])->lockForUpdate()->first();
                    if(!$booking || $booking->deposit_status!=='Paid' || $booking->is_test || $booking->payment_is_simulated || !$booking->payment_verified_at || !$item->payment_confirmed_at)return false;
                    $item->update(['user_id'=>$request->user()->id]);$booking->update(['user_id'=>$request->user()->id]);return true;
                });
                if($linked){$request->session()->forget(['real_guest_link','real_guest_verified.'.$reference]);return redirect()->route('requests.index')->with('status','Your verified request and booking are linked to your account.');}
            }
            $request->session()->forget(['real_guest_link','real_guest_verified.'.$reference]);
        }
        return redirect()->route($request->user()->landingRoute())->with('status', 'You are now signed in. Welcome back!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $request->session()->put('website_locale', 'en');

        return redirect()->route('home')->with('status', 'You have been signed out.')
            ->cookie('website_language', 'en', 525600);
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
    }
}
