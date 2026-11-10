@extends('layouts.app')
@section('title', __('Create an Account') . ' - AESTHETIC')
@section('body-class', 'auth-page')
@section('auth-page', 'true')
@section('content')
    <x-auth.auth-layout mode="register">
        <p class="auth-eyebrow">{{ __('JOIN AESTHETIC') }}</p>
        <h1 id="auth-title">{{ __('Create an Account') }}</h1>
        <p class="auth-description">{{ __('Join AESTHETIC to request personalized quotes, compare hospitals, and start your beauty journey.') }}</p>
        <x-auth.email-form :register="true" />
        <div class="auth-action"><x-auth.google-button /></div>
        <p class="auth-switch">{{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Sign In') }}</a></p>
    </x-auth.auth-layout>
@endsection
