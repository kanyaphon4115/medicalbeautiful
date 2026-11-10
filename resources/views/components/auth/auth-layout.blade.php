@props(['mode' => 'login'])
<section class="auth-scene auth-scene--{{ $mode }}" aria-labelledby="auth-title" data-auth-page>
    <img class="auth-portrait" src="{{ asset('images/hero.png') }}" alt="Elegant Korean-inspired beauty portrait in soft ivory silk" fetchpriority="high">
    <div class="auth-veil" aria-hidden="true"></div>
    <div class="auth-content container">
        <div class="auth-card">{{ $slot }}</div>
        <div class="auth-signature" aria-hidden="true">
            @if($mode === 'register')
                <span>{{ __('Begin') }}</span><span>{{ __('Your Beauty') }}</span><span>{{ __('Journey') }}</span>
            @else
                <span>{{ __('More') }}</span><span>{{ __('Confident') }}</span><span>{{ __('You') }}</span>
            @endif
            <small>{{ __('NATURAL') }}<br>{{ __('SAFE') }}<br>{{ __('TRUSTED') }}<br>{{ __('BEAUTIFUL') }}</small>
        </div>
    </div>
    <div class="auth-trust container" aria-label="Our commitment to your journey">
        <div><x-icon name="shield" size="23" /><span>{{ __('Verified Hospitals') }}</span></div>
        <div><x-icon name="headset" size="23" /><span>{{ __('Expert Consultation') }}</span></div>
        <div><x-icon name="card" size="23" /><span>{{ __('Secure & Safe') }}</span></div>
    </div>
</section>
