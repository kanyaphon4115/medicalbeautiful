<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Explore aesthetic procedures and demo hospital partners with AESTHETIC. Your beauty journey begins with confidence.">
    <meta name="theme-color" content="#FAF8F5">
    <title>@yield('title', 'AESTHETIC — Beauty for a Better You')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=DM+Sans:wght@400;450;500;550;600;650;700&family=Noto+Sans+KR:wght@400;500;600&family=Noto+Sans+Thai:wght@400;500;600&family=La+Belle+Aurore&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/procedures.css', 'resources/css/responsive.css', 'resources/js/app.js'])
</head>
<body data-request-draft-owner="{{ auth()->id() }}" data-request-profile-update-url="{{ route('account.profile.update') }}" data-request-profile-url="{{ route('requests.profile') }}" data-payment-qr-example="{{ asset('images/payment-qr-example.svg') }}" data-customer-flow="real" class="@yield('body-class')" data-request-procedures="{{ json_encode(\App\Http\Controllers\TreatmentRequestController::PROCEDURES) }}" data-request-store="{{ route('requests.store') }}" data-login-url="{{ route('login') }}" data-signed-in="{{ auth()->check() ? 'true' : 'false' }}">
    <x-navbar />
    @if(session('status'))<div class="account-status" role="status">{{ __(session('status')) }}</div>@endif
    <main>@yield('content')</main>
    @hasSection('auth-page')
    @else
        <x-footer />
    @endif
    <div class="toast" role="status" aria-live="polite"></div>
    <dialog id="demo-modal" aria-labelledby="modal-title">
        <button type="button" class="modal-close icon-button" aria-label="{{ __('Close dialog') }}"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        <div id="modal-content"></div>
    </dialog>
    <x-chat-widget />
</body>
</html>
