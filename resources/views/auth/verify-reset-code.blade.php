@extends('layouts.app')
@section('title', 'ยืนยันรหัส - AESTHETIC')
@section('body-class', 'auth-page')
@section('auth-page', 'true')
@section('content')
<x-auth.auth-layout>
    <p class="auth-eyebrow">AESTHETIC</p>
    <h1 id="auth-title">ยืนยันรหัส</h1>
    <p class="auth-description">กรอกรหัส 6 หลักที่ส่งไปยังอีเมลของคุณ รหัสหมดอายุใน 5 นาที</p>
    <form class="auth-email-form" method="POST" action="{{ route('password.verify.store') }}">
        @csrf
        <label for="verify-email">{{ __('Email Address') }}</label>
        <div class="auth-field"><input id="verify-email" name="email" type="email" value="{{ old('email', request('email')) }}" autocomplete="email" required maxlength="254"></div>
        <label for="verify-code">{{ __('6-digit verification code') }}</label>
        <div class="auth-field"><input id="verify-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required autofocus></div>
        @if($errors->any())<p class="auth-form-error" role="alert">{{ __($errors->first()) }}</p>@endif
        <button class="button button-gold auth-submit" type="submit">ยืนยันรหัส</button>
    </form>
    <p class="auth-switch"><a href="{{ route('password.request') }}">{{ __('Send reset code') }}</a></p>
</x-auth.auth-layout>
@endsection
