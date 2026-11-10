@extends('layouts.app')
@section('title', __('Sign In') . ' - AESTHETIC')
@section('body-class', 'auth-page')
@section('auth-page', 'true')
@section('content')
    <x-auth.auth-layout mode="login">
        <p class="auth-eyebrow">{{ __('Welcome Back') }}</p>
        <h1 id="auth-title">{{ __('Sign In') }}</h1>
        <p class="auth-description">{{ __('Access your account and continue your beauty journey.') }}</p>
        <x-auth.email-form  />
        <div class="auth-action"><x-auth.google-button /></div>
        <p class="auth-switch">New to AESTHETIC? <button type="button" data-how-it-works>How It Works</button></p>
    </x-auth.auth-layout>
@endsection
