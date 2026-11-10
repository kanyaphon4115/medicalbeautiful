<form method="POST" action="{{ route('guest.requests.activate',$item->reference) }}">@csrf
 @if(\App\Models\User::where('email',$item->email)->exists())
 <p>This email already has an account. Sign in securely to link your approved booking.</p>
 @else
 <label>Username<input name="username" type="email" value="{{ $item->email }}" readonly autocomplete="username"></label>
 <p class="real-guest-help">Your verified email is your username. Your personal information is already saved.</p>
 <label>Password<input name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
 <label>Confirm Password<input name="password_confirmation" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
 <label class="real-guest-consent"><input type="checkbox" name="terms" value="1" required>I agree to the Terms of Service and Privacy Policy.</label>
 @endif
 <button class="button button-gold">{{ \App\Models\User::where('email',$item->email)->exists()?'Sign In & Link Request':'Create Account & Sign In' }}</button>
</form>
