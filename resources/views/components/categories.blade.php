@php
$categories = [
 ['Eye Surgery','Brighter, more defined eyes','eye'],['Rhinoplasty','Balance and refine your profile','nose'],['Lip Surgery','Enhance your natural smile','lips'],['Facial Contouring','Harmonize your facial proportions','contour'],['Face Lifting','Refresh your youthful look','lifting'],['Chin Surgery','A more balanced and defined jawline','contour'],['Facial Fat Reduction','A slimmer, more contoured face','lifting'],['Skin Treatments','Clearer, healthier-looking skin','other']
];
@endphp
<section id="procedures" class="procedures-section container" aria-labelledby="procedures-title">
    <div class="procedure-heading"><h2 id="procedures-title">{{ __('Explore Facial Procedures') }}</h2><p>{{ __('Find the right treatment for your beauty goals.') }}</p></div>
    <div class="procedure-shell"><div class="procedure-track" id="procedure-track">
        @foreach($categories as [$name, $description, $image])
        <a href="{{ url('/') }}#journey/new?procedure={{ rawurlencode($name) }}" class="procedure-card procedure-card-{{ $loop->index }}" data-procedure="{{ $name }}" data-search="{{ strtolower($name.' '.$description) }}" aria-label="{{ __('Request a quote for') }} {{ __($name) }}"><img src="{{ asset('images/'.$image.'.jpg') }}" alt="{{ __($name) }}" loading="lazy"><h3>{{ __($name) }}</h3><p>{{ __($description) }}</p></a>
        @endforeach
    </div><button class="carousel-arrow procedure-next" data-slide="procedure-track" data-direction="1" aria-label="{{ __('Next procedures') }}"><x-icon name="chevron"/></button></div>
    <p class="empty-message" id="procedure-empty" hidden>{{ __('No matching procedures. Try a different search.') }}</p>
</section>
