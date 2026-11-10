@props(['register' => false])
<form class="auth-email-form" method="POST" action="{{ $register ? route('register.store') : route('login.store') }}" data-auth-form data-mode="{{ $register ? 'register' : 'login' }}" novalidate>
    @csrf
    @if($register)
    <label for="auth-name">{{ __('Full Name') }}</label>
    <div class="auth-field"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 21v-3a7 7 0 0 1 14 0v3H5Z"/></svg><input id="auth-name" name="name" type="text" autocomplete="name" value="{{ old('name') }}" placeholder="{{ __('Enter your full name') }}" required maxlength="80" aria-describedby="auth-form-error"></div>
    @endif
    <label for="auth-email">{{ __('Email Address') }}</label>
    <div class="auth-field"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg><input id="auth-email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" maxlength="254" placeholder="{{ __('Enter your email address') }}" required aria-describedby="auth-form-error"></div>
    <label for="auth-password">{{ __('Password') }}</label>
    <div class="auth-field"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg><input id="auth-password" name="password" type="password" autocomplete="{{ $register ? 'new-password' : 'current-password' }}" placeholder="{{ __($register ? 'Create a password' : 'Enter your password') }}" required @if($register) minlength="8" @endif aria-describedby="auth-form-error"><button type="button" class="password-toggle" data-password-toggle="auth-password" aria-label="{{ __('Show password') }}" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
    @if($register)
    <label for="auth-confirm">{{ __('Confirm Password') }}</label>
    <div class="auth-field"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg><input id="auth-confirm" name="password_confirmation" type="password" autocomplete="new-password" placeholder="{{ __('Confirm your password') }}" required aria-describedby="auth-form-error"><button type="button" class="password-toggle" data-password-toggle="auth-confirm" aria-label="{{ __('Show password') }}" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
    <label class="auth-consent"><input id="auth-terms" name="terms" value="1" type="checkbox" @checked(old('terms')) required aria-describedby="auth-form-error"><span>{{ __('I agree to the') }} <button type="button" data-info="terms">{{ __('Terms & Conditions') }}</button> {{ __('and') }} <button type="button" data-info="privacy">{{ __('Privacy Policy') }}</button></span></label>
    @else
    <a class="auth-forgot" href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
    @endif
    <p id="auth-form-error" class="auth-form-error" role="alert" @if(!$errors->any()) hidden @endif>{{ $errors->first() }}</p>
    <button class="button button-gold auth-submit" type="submit">{{ __($register ? 'Create Account' : 'Sign In') }}</button>
</form>
<div class="auth-divider"><span>{{ __('or continue with') }}</span></div>
