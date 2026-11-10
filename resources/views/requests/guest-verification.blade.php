<section id="guest-verification">
 <p>Your payment has been approved. Verify your email to continue setting up your account.</p>
 <button type="button" class="button button-gold" data-open-guest-otp>Verify Your Email</button>
 <dialog class="guest-otp-dialog" data-guest-otp-dialog data-auto-open aria-labelledby="guest-otp-title">
  <button type="button" class="guest-otp-close" data-close-guest-otp aria-label="Close verification">&times;</button>
  <div class="guest-otp-envelope" aria-hidden="true">&#9993;</div>
  <p class="eyebrow">AESTHETIC</p><h2 id="guest-otp-title">Verify Your Email</h2>
  <p>Enter the 6-digit code sent to your email.</p><p class="guest-otp-address">{{ $item->email }}</p>
  <form method="POST" action="{{ route('guest.requests.verify',$item->reference) }}" data-guest-otp-form>@csrf
   <div class="guest-otp-digits">
   @for($i=0;$i<6;$i++)<input name="digits[]" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" required autocomplete="{{ $i===0?'one-time-code':'off' }}" aria-label="Digit {{ $i+1 }}" data-otp-digit>@endfor
   </div>
   <p class="guest-otp-feedback" role="status" aria-live="polite" data-otp-feedback>{{ $errors->first('code') ?: session('status') }}</p>
   <button class="button button-gold" type="submit">Verify</button>
  </form>
  <p class="real-guest-help">The code expires in 10 minutes and can be used once.</p>
  <form method="POST" action="{{ route('guest.requests.code',$item->reference) }}" data-guest-otp-resend>@csrf
   <button type="submit" class="guest-otp-resend" data-cooldown="{{ \Illuminate\Support\Facades\RateLimiter::availableIn('real-guest-code:'.$item->reference.':send') }}">Resend Code</button>
  </form>
 </dialog>
 <noscript><p>Open the verification dialog using a JavaScript-enabled browser, or enter the code below.</p><form method="POST" action="{{ route('guest.requests.verify',$item->reference) }}">@csrf<label>Verification Code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required></label><button class="button button-gold">Verify</button></form></noscript>
</section>
