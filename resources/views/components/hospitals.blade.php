@php
$hospitals = [
 ['Aesthetic Hospital Bangkok','Bangkok, Thailand','4.9','512','hospital-1'],['Beauty Plus Hospital','Bangkok, Thailand','4.8','430','hospital-2'],['The Signature Clinic','Seoul, South Korea','4.7','320','hospital-3'],['Prime Aesthetic Center','Bangkok, Thailand','4.8','290','hospital-4'],['Grace Aesthetic Hospital','Chiang Mai, Thailand','4.6','218','hospital-5'],['Wellness Clinic','Phuket, Thailand','4.7','195','hospital-6']
];
@endphp
<section class="hospital-section container" id="hospitals" data-partner-directory data-assets="{{ asset('images') }}" aria-labelledby="hospitals-title">
    <div class="section-heading"><div><p class="eyebrow">{{ __('PARTNER HOSPITALS') }}</p><h2 id="hospitals-title">{{ __('Discover Our Partner Hospitals') }}</h2><p>{{ __('Explore aesthetic hospitals and clinics to find the right provider for your needs.') }}</p></div><button class="text-link" id="view-all">{{ __('View all hospitals') }} <x-icon name="arrow" size="16"/></button></div>
    <div class="demo-note"><span></span> {{ __('Demo collection · All hospitals, ratings, and review counts below are fictional.') }}</div>
    <div class="hospital-shell"><button class="carousel-arrow hospital-prev" data-slide="hospital-track" data-direction="-1" aria-label="{{ __('Previous hospitals') }}"><x-icon name="chevron"/></button><div class="hospital-track" id="hospital-track">
        @foreach($hospitals as [$name, $location, $rating, $reviews, $image])
        <article class="hospital-card" data-search="{{ strtolower($name.' '.$location) }}">
            <div class="hospital-image"><img src="{{ asset('images/'.$image.'.jpg') }}" alt="Illustrative exterior or interior of {{ __($name) }}" loading="lazy"><span class="demo-badge">{{ __('DEMO') }}</span><button class="favorite" aria-label="Save {{ __($name) }}" aria-pressed="false" data-name="{{ __($name) }}"><x-icon name="heart" size="22"/></button></div>
            <div class="hospital-body"><h3><a href="#hospital/{{ \Illuminate\Support\Str::slug($name) }}">{{ __($name) }}</a></h3><p class="location"><x-icon name="pin" size="14"/> {{ $location }}</p></div>
        </article>
        @endforeach
    </div><button class="carousel-arrow hospital-next" data-slide="hospital-track" data-direction="1" aria-label="{{ __('Next hospitals') }}"><x-icon name="chevron"/></button></div>
    <p class="empty-message" id="hospital-empty" hidden>{{ __('No matching demo hospitals. Try another name or location.') }}</p>
</section>
