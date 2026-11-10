<section class="hero">
    <img class="hero-image" src="{{ asset('images/hero.png') }}" alt="Korean-inspired beauty portrait with soft ivory silk and natural makeup" fetchpriority="high">
    <div class="hero-inner">
        <div class="hero-copy"><p class="eyebrow">{{ __('YOUR BEAUTY JOURNEY') }}</p><h1>{{ __('Your Beauty,') }}<br><em>{{ __('Your Confidence.') }}</em></h1><p class="hero-description">{{ __('Discover trusted aesthetic hospitals, compare treatment options, receive personalized quotations, and begin your beauty journey with confidence.') }}</p><div class="hero-buttons"><button class="button button-gold" data-quote>{{ __('Get a Free Quote') }} <x-icon name="arrow" size="18" /></button><button class="button button-outline" data-info="how"><x-icon name="play" size="22" /> {{ __('How It Works') }}</button></div></div>
        <div class="hero-benefits">
            <div><x-icon name="sparkle" size="30"/><span>{{ __('Direct Communication,') }}<br>{{ __('Various Opinions & Comparison') }}</span></div><div><x-icon name="shield" size="30"/><span>{{ __('Verified Hospital') }}<br>{{ __('Partners') }}</span></div><div><x-icon name="headset" size="30"/><span>{{ __('Personalized') }}<br>{{ __('Consultation') }}</span></div><div><x-icon name="card" size="30"/><span>{{ __('Secure Booking') }}<br>{{ __('Experience') }}</span></div>
        </div>
    </div>
    <div class="hero-signature" aria-hidden="true">{{ __('More') }}<br><span>{{ __('Confident') }}</span><br><span class="signature-you">{{ __('You') }}</span><small>{{ __('NATURAL') }}<br>{{ __('SAFE') }}<br>{{ __('TRUSTED') }}<br>{{ __('BEAUTIFUL') }}</small></div>
</section>
