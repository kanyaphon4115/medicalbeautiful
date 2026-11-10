@extends('layouts.app')
@section('title', __('Reset password') . ' - AESTHETIC')
@section('body-class', 'auth-page')
@section('auth-page', 'true')
@section('content')
<x-auth.auth-layout>
    <p class="auth-eyebrow">AESTHETIC</p>
    <h1 id="auth-title">{{ __('Reset password') }}</h1>
    <form class="auth-email-form" method="POST" action="{{ route('password.update') }}">
        @csrf

        <label for="reset-email">{{ __('Email Address') }}</label>
        <div class="auth-field"><input id="reset-email" name="email" type="email" value="{{ $email }}" autocomplete="email" readonly></div>
        <label for="reset-password">{{ __('Password') }}</label>
        <div class="auth-field"><input id="reset-password" name="password" type="password" autocomplete="new-password" minlength="8" required></div>
        <label for="reset-confirm">{{ __('Confirm Password') }}</label>
        <div class="auth-field"><input id="reset-confirm" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></div>
        @if($errors->any())<p class="auth-form-error" role="alert">{{ __($errors->first()) }}</p>@endif
        <button class="button button-gold auth-submit" type="submit">{{ __('Reset password') }}</button>
    </form>
    <p class="auth-switch"><a href="{{ route('password.request') }}">{{ __('Send reset code') }}</a></p>
</x-auth.auth-layout>
@endsection
