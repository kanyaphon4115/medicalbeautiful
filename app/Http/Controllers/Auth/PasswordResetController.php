<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    private function normalize(Request $request): void
    {
        if (is_string($request->email)) {
            $request->merge(['email' => Str::lower(trim($request->email))]);
        }
    }

    public function send(Request $request)
    {
        $this->normalize($request);
        $data = $request->validate(['email' => ['required', 'email', 'max:254']]);
        if (in_array(config('mail.default'), ['log', 'array'], true) && !app()->runningUnitTests()) {
            throw ValidationException::withMessages(['email' => __('Email delivery is not configured. Please contact the administrator.')]);
        }
        try {
            $key = 'reset-send:'.hash('sha256', $data['email']);
            if (!RateLimiter::tooManyAttempts($key, 1)) {
                RateLimiter::hit($key, 60);
                $user = User::where('email', $data['email'])->first();
                if ($user) {
                    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    DB::table('password_reset_tokens')->updateOrInsert(['email' => $data['email']], ['token' => Hash::make($code), 'created_at' => now()]);
                    $user->notify(new \App\Notifications\PasswordResetCode($code));
                }
            }
        } catch (\Throwable $error) {
            // Do not log SMTP exceptions, which may contain authentication data.
            throw ValidationException::withMessages(['email' => __('Email could not be sent. Please try again later.')]);
        }
        return redirect()->route('password.verify', ['email' => $data['email']])->with('status', 'If this email is registered, a reset code will be sent. Check your inbox and spam folder.');
    }

    public function verify(Request $request)
    {
        $this->normalize($request);
        $data = $request->validate(['email'=>['required','email','max:254'], 'code'=>['required','digits:6']]);
        $key = 'reset-verify:'.hash('sha256', $data['email']);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code'=>__('Too many attempts. Please try again in 5 minutes.')]);
        }
        RateLimiter::hit($key, 300);
        $grant = DB::transaction(function () use ($data) {
            $row = DB::table('password_reset_tokens')->where('email',$data['email'])->lockForUpdate()->first();
            if (!$row || !$row->created_at || \Illuminate\Support\Carbon::parse($row->created_at)->addMinutes(5)->isPast() || !Hash::check($data['code'],$row->token)) return null;
            $secret = Hash::make(Str::random(64));
            DB::table('password_reset_tokens')->where('email',$data['email'])->update(['token'=>$secret]);
            return ['email'=>$data['email'], 'token'=>$secret, 'expires'=>\Illuminate\Support\Carbon::parse($row->created_at)->addMinutes(5)->timestamp];
        });
        if (!$grant) throw ValidationException::withMessages(['code'=>__('The reset code is incorrect or expired. Please request a new code.')]);
        $request->session()->regenerate();
        $request->session()->put('password_reset_grant',$grant);
        return redirect()->route('password.reset');
    }

    public function form(Request $request)
    {
        $grant = $request->session()->get('password_reset_grant');
        if (!$grant || $grant['expires'] <= now()->timestamp) return redirect()->route('password.request');
        return view('auth.reset-password', ['email'=>$grant['email']]);
    }

    public function reset(Request $request)
    {
        $this->normalize($request);
        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', 'min:8', function ($attribute, $value, $fail) {
                if (strlen($value) > 72) $fail(__('The password must not exceed 72 bytes.'));
            }],
        ]);
        $grant = $request->session()->get('password_reset_grant');
        if (!$grant || $grant['expires'] <= now()->timestamp) return redirect()->route('password.request');
        $data['email'] = $grant['email'];
        $changed = DB::transaction(function () use ($data, $grant) {
            $row = DB::table('password_reset_tokens')->where('email', $data['email'])->lockForUpdate()->first();
            if (!$row || !$row->created_at || \Illuminate\Support\Carbon::parse($row->created_at)->addMinutes(5)->isPast() || !hash_equals($grant['token'], $row->token)) return false;
            $user = User::where('email', $data['email'])->first();
            if (!$user) return false;
            $user->password = $data['password'];
            $user->setRememberToken(Str::random(60));
            $user->save();
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            event(new PasswordReset($user));
            return true;
        });
        if (!$changed) {
            throw ValidationException::withMessages(['code' => __('The reset code is incorrect or expired. Please request a new code.')]);
        }
        $request->session()->forget('password_reset_grant');
        return redirect()->route('login')->with('status', 'Your password has been reset. Please sign in with your new password.');
    }
}
