@extends('layouts.app')
@section('title', 'My Requests - AESTHETIC')
@section('body-class', 'requests-page')
@section('content')
<section class="requests-workspace" data-requests-page data-deposit-satang="{{ \App\Services\DepositPayment::DUE_NOW_SATANG }}" data-assets="{{ asset('images') }}">
    <script type="application/json" id="request-pagination">{!! Illuminate\Support\Js::encode(['page'=>$requestPage->currentPage(),'total'=>$requestPage->total(),'pages'=>$requestPage->lastPage(),'counts'=>$filterCounts,'filter'=>$filters['filter']??'all','sort'=>$filters['sort']??'newest']) !!}</script>
    <script type="application/json" id="saved-requests">@json($requests, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
    <div class="requests-container" id="my-requests">
        <div class="requests-heading"><div><p class="eyebrow">{{ __('MY REQUESTS') }}</p><h1>{{ __('Your Beauty Requests') }}</h1><p>{{ __('Track your submitted requests and compare quotes from our partner hospitals.') }}</p></div><button class="button button-gold request-new" data-quote><span aria-hidden="true">+</span> {{ __('New Request') }}</button></div>
        <div class="request-controls"><div class="request-filters" role="group" aria-label="{{ __('Filter requests by status') }}">
            @foreach(['all' => 'All Requests', 'in_progress' => 'In Progress', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)
            <button type="button" data-request-filter="{{ $value }}" aria-pressed="{{ $value === 'all' ? 'true' : 'false' }}">{{ __($label) }} <span data-filter-count="{{ $value }}">{{ $filterCounts[$value] ?? 0 }}</span></button>
            @endforeach
        </div><label class="request-sort">{{ __('Sort by') }} <select id="request-sort"><option value="newest">{{ __('Newest First') }}</option><option value="oldest">{{ __('Oldest First') }}</option></select></label></div>
        <p class="requests-demo-label">{{ __('Your requests are private. Compare quotation prices and treatment details below.') }}</p>
        <div class="requests-list" id="requests-list" aria-label="{{ __('Your requests') }}"></div>
        <div class="requests-pagination-panel"><p class="requests-results" role="status" aria-live="polite" id="requests-results"></p><nav class="requests-pagination" id="requests-pagination" aria-label="{{ __('Request pages') }}" hidden></nav></div>
        <div class="request-list-empty" id="request-list-empty" hidden><x-icon name="document" size="40"/><h2>{{ __('No requests to show') }}</h2><p>{{ __('Try another filter or start a new beauty request.') }}</p><button class="button button-gold" data-quote>{{ __('New Request') }}</button></div>
        <noscript><p class="request-list-empty">{{ __('Please enable JavaScript to explore your requests.') }}</p></noscript>
    </div>
</section>

<template id="request-card-template"><x-requests.request-card /></template>
<template id="hospital-response-template"><x-requests.hospital-response-card /></template>
@endsection
