<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $catalog[$pages[$page]] }} | AESTHETIC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600;700&family=Noto+Sans+Thai:wght@400;500;600&family=Noto+Sans+KR:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css','resources/css/admin.css','resources/css/responsive.css','resources/js/admin/portal.js'])
</head>
<body class="ap-body" data-page="{{ $page }}">
<aside class="ap-sidebar" id="ap-sidebar">
    <a class="brand" href="{{ route('home') }}"><span class="brand-mark">A</span><span><strong>AESTHETIC</strong><small>BEAUTY FOR A BETTER YOU</small></span></a>
    <nav aria-label="{{ $catalog['Admin navigation'] }}">
        @foreach($pages as $slug=>$label)
        <a href="{{ route('admin.'.$slug, ['lang'=>$locale]) }}" @if($slug===$page) aria-current="page" @endif><span data-ap-icon="{{ $slug }}"></span>{{ $catalog[$label] }}</a>
        @endforeach
    </nav>
    <div class="ap-sidebar-bottom"><span>{{ in_array($page,['payments','bookings']) ? 'Database records' : $catalog['Frontend demo'] }}</span><a href="{{ route('home') }}">{{ $catalog['Back to website'] }} ↗</a></div>
</aside>
<div class="ap-shell">
    <header class="ap-header"><button type="button" id="ap-menu" aria-expanded="false" aria-controls="ap-sidebar" aria-label="{{ $catalog['Toggle navigation'] }}">☰</button><span class="ap-workspace">{{ $catalog['Super Admin Portal'] }}</span><label class="ap-language"><span class="ap-sr">{{ $catalog['Language'] }}</span><select id="ap-language"><option value="en" @selected($locale==='en')>English</option><option value="th" @selected($locale==='th')>ไทย</option><option value="ko" @selected($locale==='ko')>한국어</option></select></label><button type="button" class="ap-notifications" data-action="notifications" aria-label="{{ $catalog['Notifications'] }}"><span data-ap-icon="notifications"></span><i></i></button><details class="ap-account"><summary><span class="ap-avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><span>{{ auth()->user()->name }}<small>{{ $catalog['Super Admin'] }}</small></span><span>⌄</span></summary><div><a href="{{ route('home') }}">{{ $catalog['Back to website'] }}</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">{{ $catalog['Sign Out'] }}</button></form></div></details></header>
    <main class="ap-main" id="ap-main">
        @if(!in_array($page, ['settings','support','requests','payments','bookings']))<div class="ap-demo"><span>{{ $catalog['Frontend demo'] }}</span> {{ $catalog['Sample data only. Changes stay in this tab. No real payments, messages or account changes.'] }}<button type="button" data-action="reset">{{ $catalog['Reset demo'] }}</button></div>@endif
        <section class="ap-page-heading @if($page==='dashboard') ap-welcome @endif">
            <div>@if($page==='dashboard')<p class="eyebrow">{{ $catalog['PLATFORM OVERVIEW'] }}</p><h1>{{ $catalog['Welcome back,'] }} {{ auth()->user()->name }}</h1>@else<h1>{{ $catalog[$pages[$page]] }}</h1>@endif
            @if(!in_array($page, ['settings','support','requests','payments','bookings']))<p>{{ $catalog['intro_'.$page] }}</p>@endif</div>
        </section>
        <div id="ap-content" aria-live="polite">@if($page==='requests')@include('admin.real-requests')@endif
        @if(in_array($page,['payments','bookings']))@include('admin.real-payments')@endif</div>
        <noscript>{{ $catalog['Please enable JavaScript to use this interactive preview.'] }}</noscript>
    </main>
</div>
<dialog id="ap-dialog" aria-labelledby="ap-dialog-title"><button type="button" class="ap-dialog-close" data-action="close" aria-label="{{ $catalog['Close'] }}">×</button><p class="eyebrow">{{ $page==='settings' ? $catalog['Settings'] : $catalog['Frontend demo'] }}</p><h2 id="ap-dialog-title"></h2><div id="ap-dialog-body"></div></dialog>
<div id="ap-toast" role="status" aria-live="polite"></div>
@php
    $portalData = ['page'=>$page,'locale'=>$locale,'catalog'=>$catalog,'base'=>url('/admin'),'assets'=>asset('images'),'name'=>auth()->user()->name,'userId'=>auth()->id(),'chatReadEndpoint'=>route('chat.read'),'chatEndpoint'=>route('chat.index'),'chatSessionEndpoint'=>route('chat.session')];
@endphp
<script type="application/json" id="ap-data">@json($portalData)</script>
</body>
</html>
