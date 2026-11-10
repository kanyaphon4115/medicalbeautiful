@extends('layouts.app')
@section('title', __('Forgot your password?') . ' - AESTHETIC')
@section('body-class', 'auth-page')
@section('auth-page', 'true')
@section('content')
<x-auth.auth-layout>
    <p class="auth-eyebrow">AESTHETIC</p>
    <h1 id="auth-title">{{ __('Forgot your password?') }}</h1>
    <p class="auth-description">{{ __('Enter your registered email to receive a 6-digit reset code.') }}</p>
    <form class="auth-email-form" method="POST" action="{{ route('password.email') }}">
        @csrf
        <label for="reset-email">{{ __('Email Address') }}</label>
        <div class="auth-field"><input id="reset-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required maxlength="254"></div>
        @error('email')<p class="auth-form-error" role="alert">{{ __($message) }}</p>@enderror
        @if(session('status'))<p role="status">{{ __(session('status')) }}</p>@endif
        <button class="button button-gold auth-submit" type="submit">{{ __('Send reset code') }}</button>
    </form>
    <p class="auth-switch"><a href="{{ route('login') }}">{{ __('Back to sign in') }}</a></p>
</x-auth.auth-layout>
@endsection
