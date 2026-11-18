@extends('layouts.app')
@section('title','Patient Reviews | AESTHETIC')
@section('body-class','patient-reviews-page')
@section('content')
<section class="pr-hero" aria-labelledby="pr-title" style="--pr-hero-image:url('{{ asset('images/hero.png') }}')">
 <div class="pr-hero-inner">
  <p class="pr-eyebrow">REAL PATIENT EXPERIENCES</p>
  <h1 id="pr-title">Patient Reviews</h1>
  <a class="pr-write" href="{{ route('reviews.create') }}">Write a Review <x-icon name="arrow" size="19"/></a>
  <div class="pr-statistics" aria-label="Patient review statistics">
   <div><x-icon name="users" size="36"/><p><strong>{{ number_format($stats['count']) }}</strong><span>Patient Reviews</span></p></div>
   <div><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8l-6.2 3.2L7 14.2l-5-4.9 6.9-1Z"/></svg><p><strong>{{ $stats['average']===null?'—':number_format($stats['average'],1).' / 5' }}</strong><span>Average Rating</span></p></div>
   <div><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 6h4l2-3h6l2 3h4v15H3Z"/><circle cx="12" cy="13" r="5"/><path d="M18 9h1"/></svg><p><strong>{{ number_format($stats['photos']) }}</strong><span>Patient Photos</span></p></div>
  </div>
 </div>
 <p class="pr-hero-script" aria-hidden="true">Real<br><span>Patient</span><br><em>Reviews</em></p>
</section>
<section class="pr-content" aria-label="Patient reviews">
 <form method="GET" action="{{ route('reviews.index') }}" class="pr-filters" data-review-filters role="search">
  <a class="pr-all {{ !request()->hasAny(['q','procedure','rating','photos'])?'active':'' }}" href="{{ route('reviews.index') }}">All Reviews</a>
  <label class="pr-select"><span class="sr-only">Rating</span><select name="rating" aria-label="Rating"><option value="">Rating</option>@foreach([5,4,3,2,1] as $rating)<option value="{{ $rating }}" @selected(($filters['rating']??'')==$rating)>{{ $rating }} stars</option>@endforeach</select></label>
  <label class="pr-search"><x-icon name="search" size="20"/><input type="search" name="q" value="{{ $filters['q']??'' }}" maxlength="200" aria-label="Search reviews" placeholder="Search reviews (procedure, keyword)"></label>
  <button class="pr-filter-submit" type="submit">Search</button>
 </form>
 @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
 <div class="pr-cards">
 @forelse($reviews as $review)
  <article class="pr-card" id="review-{{ $review->id }}" style="scroll-margin-top:24px">
   <div class="pr-results {{ $review->has_photos?'':'pr-no-photos' }}" @if($review->has_photos) tabindex="0" role="region" aria-label="Patient photos" @endif>
   @if($review->has_photos)
    @foreach($review->gallery as $photo)<figure><img src="{{ $photo['url'] }}" alt="Patient photo shared with consent" loading="lazy"></figure>@endforeach
   @else <x-icon name="heart" size="32"/><span>Patient experience</span><small>No photos shared</small>@endif
   </div>
   <div class="pr-card-copy"><div class="pr-card-top"><span class="pr-category">{{ $review->procedure }}</span><span class="pr-rating"><x-review-stars :rating="$review->rating"/><b>{{ number_format($review->rating,1) }}</b></span></div>
    @if($review->title)<h2 data-user-content>{{ $review->publicText($review->title) }}</h2>@endif
    <p data-user-content class="pr-excerpt">{{ \Illuminate\Support\Str::limit($review->publicText($review->body),210) }}</p>
    @if(mb_strlen($review->publicText($review->body))>210)<details class="pr-inline-review"><summary>Read full review</summary><p data-user-content>{{ $review->publicText($review->body) }}</p></details>@endif
    <div class="pr-author"><span class="pr-patient-avatar" aria-hidden="true"><x-icon name="users" size="17"/></span><span data-user-content>{{ $review->masked_name }}</span><i></i><time datetime="{{ $review->published_at->toDateString() }}" title="{{ $review->published_at->format('M j, Y') }}">{{ $review->published_at->locale(app()->getLocale())->diffForHumans() }}</time></div>
   </div>
  </article>
 @empty
  <div class="pr-empty"><x-icon name="heart" size="36"/><h2>{{ request()->hasAny(['q','procedure','rating','photos'])?'No reviews match your filters.':'Patient stories start here.' }}</h2><p>{{ $stats['count']?'Try another procedure, rating, or search term.':'Published patient reviews and authorized patient photos will appear here.' }}</p><a href="{{ route('reviews.create') }}" class="pr-write">Write a Review <x-icon name="arrow" size="19"/></a></div>
 @endforelse
 </div>
 <div class="pr-pagination">{{ $reviews->links() }}</div>
</section>
@include('reviews.write-dialog')
@endsection
