<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! $this->configured()) {
            return $this->failure('Google Sign-In is not configured yet. Please use email sign-in for now.');
        }

        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])
            ->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->configured()) {
            return $this->failure('Google Sign-In is not configured yet. Please use email sign-in for now.');
        }
        if ($request->has('error')) {
            $request->session()->forget('state');
            return $this->failure('Google sign-in was cancelled. You can try again.');
        }

        try {
            // Keep Socialite session-state validation enabled to prevent login CSRF.
            $google = Socialite::driver('google')->user();
            $raw = $google->getRaw();
            $verified = $raw['email_verified'] ?? $raw['verified_email'] ?? false;
            $email = Str::lower(trim((string) $google->getEmail()));
            $id = (string) $google->getId();
            if (! in_array($verified, [true, 'true', 1, '1'], true) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $id === '' || strlen($id) > 255) {
                return $this->failure('Google could not provide a verified email address. Please use another account.');
            }

            $user = User::where('google_id', $id)->first();
            if (! $user) {
                // Do not silently attach Google to an existing password account.
                if (User::where('email', $email)->exists()) {
                    return $this->failure('An account with this email already exists. Please sign in with your password.');
                }
                $user = new User();
                $user->name = Str::limit(trim((string) $google->getName()) ?: Str::before($email, '@'), 80, '');
                $user->email = $email;
                $user->google_id = $id;
                $user->email_verified_at = now();
                $user->password = Hash::make(Str::random(64));
                $user->save();
            }

            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->route($user->landingRoute())->with('status', 'You are now signed in with Google. Welcome to AESTHETIC!');
        } catch (InvalidStateException $exception) {
            return $this->failure('Your Google sign-in session expired. Please try again.');
        } catch (Throwable $exception) {
            // Provider errors can contain tokens; never expose them in the UI or logs.
            return $this->failure('Google sign-in could not be completed. Please try again or sign in with email.');
        }
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret')) && filled(config('services.google.redirect'));
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => $message]);
    }
}
