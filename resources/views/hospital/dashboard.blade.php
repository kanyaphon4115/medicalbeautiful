<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Hospital Dashboard | AESTHETIC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/hospital.css', 'resources/css/responsive.css', 'resources/js/hospital.js'])
</head>
<body class="hospital-dashboard">
@php
 $menus=['Dashboard'=>'home','Patient Requests'=>'document','My Quotations'=>'document','Bookings'=>'calendar','Booking Chat'=>'chat','Hospital Profile'=>'hospital','Notifications'=>'bell','Settings'=>'settings'];
 $images=['Eye Surgery'=>'eye','Rhinoplasty'=>'nose','Lip Surgery'=>'lips','Facial Contouring'=>'contour','Face Lifting'=>'lifting','Liposuction'=>'body','Breast Surgery'=>'breast'];
 $reference=fn($r)=>sprintf('REQ-%s-%06d',$r->created_at->format('Y'),$r->id);
@endphp
<aside class="hd-sidebar" id="hospital-sidebar">
    <a class="brand" href="{{ route('home') }}"><span class="brand-mark">A</span><span><strong>AESTHETIC</strong><small>BEAUTY FOR A BETTER YOU</small></span></a>
    <div class="hd-clinic"><img src="{{ asset('images/hospital-1.jpg') }}" alt="Hospital workspace"><strong>{{ auth()->user()->name }}</strong><span>{{ __('Hospital Admin') }}</span></div>
    <nav aria-label="{{ __('Hospital navigation') }}">
        @foreach($menus as $label=>$icon)
        <button type="button" data-hd-section="{{ $label }}" @if($loop->first) aria-current="page" @endif><span class="hd-icon" data-hd-icon="{{ $icon }}"></span>{{ __($label) }}</button>
        @endforeach
    </nav>
    <div class="hd-help"><x-icon name="headset"/><div><strong>{{ __('Need Help?') }}</strong><p>{{ __('Contact Platform Support') }}</p></div><button type="button" data-hd-support class="button button-outline">{{ __('Contact Support') }}</button></div>
</aside>
<div class="hd-shell">
    <header class="hd-header"><button type="button" class="hd-menu" aria-label="{{ __('Toggle navigation') }}" aria-expanded="false" aria-controls="hospital-sidebar">☰</button><span class="hd-header-label">{{ __('Hospital workspace') }}</span><button type="button" class="hd-bell" data-hd-section="Notifications" aria-label="{{ __('Notifications') }}"><span data-hd-icon="bell"></span></button><details class="hd-account"><summary><span class="hd-avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><span>{{ __('Hello,') }} {{ auth()->user()->name }}<small>{{ __('Hospital Admin') }}</small></span><span>⌄</span></summary><div><a href="{{ route('home') }}">{{ __('Back to homepage') }}</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">{{ __('Sign Out') }}</button></form></div></details></header>
    <main>
        <section class="hd-hero"><img src="{{ asset('images/hero.png') }}" alt="Aesthetic beauty portrait"><div><p class="eyebrow">{{ __('HOSPITAL DASHBOARD') }}</p><h1>{{ __('Welcome Back,') }}<br><span>{{ auth()->user()->name }}</span></h1><p>{{ __("Here is an overview of your hospital’s activity.") }}</p></div></section>
        <div class="hd-content">
            @if(session('status'))<p role="status">{{ __(session('status')) }}</p>@endif
            @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
            <section class="hd-stats" aria-label="{{ __('Activity overview') }}">
                @foreach($stats as $label=>$value)
                <button type="button" data-hd-section="{{ $label==='New Requests'?'Patient Requests':($label==='Quotations Sent'?'My Quotations':'Bookings') }}"><span class="hd-stat-icon" data-hd-icon="{{ str_contains($label,'Bookings')?'calendar':'document' }}"></span><span><small>{{ __($label) }}</small><strong>{{ $value }}</strong></span></button>
                @endforeach
            </section>
            <div id="hd-dashboard-panel"><div class="hd-columns"><div class="hd-main-column">
                <section class="hd-panel hd-requests"><div class="hd-panel-heading"><h2>{{ __('Recent Patient Requests') }}</h2><button type="button" data-hd-section="Patient Requests">{{ __('View all') }}</button></div>
                    <div id="hd-request-table">@include('hospital.partials.requests')</div>
                </section>
                <div class="hd-charts">
                    <section class="hd-panel"><h2>{{ __('Request Status') }}</h2><div class="hd-chart-content"><div class="hd-donut requests" style="background:conic-gradient(#b48b4a 0% {{ $requests->count() ? $stats['New Requests']/$requests->count()*100 : 0 }}%,#e9dfcf 0% 100%)"><span><strong>{{ $requests->count() }}</strong><small>{{ __('Total') }}</small></span></div><ul><li>{{ __('New Request') }} <strong>{{ $stats['New Requests'] }}</strong></li><li>{{ __('Quotation Sent') }} <strong>{{ $requests->filter(fn($r)=>$r->quotations->isNotEmpty())->count() }}</strong></li></ul></div></section>
                    <section class="hd-panel"><h2>{{ __('Booking Status') }}</h2><div class="hd-chart-content"><div class="hd-donut bookings" style="background:conic-gradient(#b48b4a 0% {{ $bookings->count() ? $stats['Confirmed Bookings']/$bookings->count()*100 : 0 }}%,#e9dfcf 0% 100%)"><span><strong>{{ $bookings->count() }}</strong><small>{{ __('Total') }}</small></span></div><ul><li>{{ __('Confirmed') }} <strong>{{ $stats['Confirmed Bookings'] }}</strong></li><li>{{ __('Completed') }} <strong>{{ $stats['Completed Bookings'] }}</strong></li></ul></div></section>
                </div>
            </div><div class="hd-right-column">
                <section class="hd-panel hd-appointments"><div class="hd-panel-heading"><h2>{{ __('Upcoming Appointments') }}</h2><button type="button" data-hd-section="Bookings">{{ __('View all') }}</button></div>
                    @forelse($bookings->filter(fn($b)=>$b->status==='Confirmed' && $b->appointment_date && $b->appointment_date->gte(today()))->sortBy('appointment_date')->take(5) as $booking)
                    <button class="hd-appointment" data-hd-section="Bookings"><span>{{ $booking->appointment_date->format('d M Y') }}</span><strong>{{ __($booking->treatmentRequest->procedure_label) }}</strong><small>{{ $reference($booking->treatmentRequest) }}</small></button>
                    @empty<p class="hd-empty">{{ __('No upcoming appointments.') }}</p>@endforelse
                </section>
                <section class="hd-panel hd-messages"><h2>{{ __('Recent Messages') }}</h2><p class="hd-empty">{{ __('Hospital booking chat is not connected yet.') }}</p></section>
            </div></div></div>
            <section class="hd-panel hd-subpanel" id="hd-section-panel" hidden><div class="hd-panel-heading"><h2 id="hd-section-title"></h2><button data-hd-section="Dashboard">{{ __('Dashboard') }}</button></div><div id="hd-section-content"></div></section>
            <template id="hd-quotations"><div class="hd-table-wrap"><table class="hd-real-table"><thead><tr>@foreach(['Request ID','Procedure','Amount','Valid until','Details','Status','Actions'] as $label)<th>{{ __($label) }}</th>@endforeach</tr></thead><tbody>
                @forelse($quotations as $quote)<tr><td>{{ $reference($quote->treatmentRequest) }}</td><td>{{ __($quote->treatmentRequest->procedure_label) }}</td><td>{{ $quote->currency }} {{ number_format($quote->amount,2) }}</td><td>{{ $quote->valid_until->format('d M Y') }}</td><td>{{ $quote->details }}</td><td>{{ $quote->withdrawn_at ? __('Withdrawn') : __('Sent') }}</td><td>@if(!$quote->withdrawn_at && !$quote->treatmentRequest->booking && !$quote->treatmentRequest->selected_hospital_id)<form method="POST" action="{{ route('hospital.quotations.withdraw',$quote) }}">@csrf<button class="button button-outline" type="submit">{{ __('Withdraw Quotation') }}</button></form>@endif</td></tr>@empty<tr><td colspan="7" class="hd-empty">{{ __('No quotations sent.') }}</td></tr>@endforelse
            </tbody></table></div></template>
            <template id="hd-bookings"><div class="hd-table-wrap"><table class="hd-real-table"><thead><tr>@foreach(['Request ID','Procedure','Preferred Date','Booking Status','Deposit Status','Actions'] as $label)<th>{{ __($label) }}</th>@endforeach</tr></thead><tbody>
                @forelse($bookings as $booking)<tr><td>{{ $reference($booking->treatmentRequest) }}</td><td>{{ __($booking->treatmentRequest->procedure_label) }}</td><td>{{ $booking->appointment_date?->format('d M Y') ?? __('Not specified') }}</td><td><span class="hd-badge green">{{ __($booking->status) }}</span></td><td><span class="hd-badge {{ $booking->deposit_status==='Paid'?'green':'yellow' }}">{{ __($booking->payment_status) }}</span>@if($booking->payment_is_simulated)<small>Simulated test payment</small>@endif</td><td>@if($booking->status==='Confirmed' && $booking->deposit_status==='Paid')<form method="POST" action="{{ route('hospital.complete',$booking) }}">@csrf<button class="hd-view">{{ __('Mark completed') }}</button></form>@endif</td></tr>@empty<tr><td colspan="6" class="hd-empty">{{ __('No bookings yet.') }}</td></tr>@endforelse
            </tbody></table></div></template>
        </div>
    </main>
</div>
@foreach($requests as $item)
<dialog class="hd-dialog" id="request-{{ $item->id }}"><button type="button" class="hd-close" aria-label="{{ __('Close') }}">&times;</button><h2>{{ $reference($item) }}</h2>@include('requests.partials.details')
@if(!$item->selected_hospital_id)<form class="hd-quote-form" method="POST" action="{{ route('hospital.quote',$item) }}">@csrf<h3>{{ __('Submit quotation') }}</h3><label>{{ __('Amount') }} (THB)<input name="amount" type="number" min="0.01" max="9999999999.99" step="0.01" value="{{ $item->quotations->first()?->amount }}" required></label><label>{{ __('Details') }}<textarea name="details" maxlength="5000" required>{{ $item->quotations->first()?->details }}</textarea></label><label>{{ __('Valid until') }}<input name="valid_until" type="date" min="{{ today()->format('Y-m-d') }}" value="{{ $item->quotations->first()?->valid_until?->format('Y-m-d') }}" required></label><button class="button button-gold">{{ __('Send quotation') }}</button></form>@endif
@if(($activeQuote=$item->quotations->first()) && !$item->selected_hospital_id && !$item->booking)<form method="POST" action="{{ route('hospital.quotations.withdraw',$activeQuote) }}">@csrf<button class="button button-outline" type="submit">{{ __('Withdraw Quotation') }}</button></form>@endif
</dialog>
@endforeach
</body></html>
