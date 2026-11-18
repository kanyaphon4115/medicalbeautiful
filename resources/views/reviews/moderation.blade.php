@extends('layouts.app')
@section('title','Review Moderation | AESTHETIC')
@section('body-class','patient-reviews-page')
@section('content')
<section class="pr-form-page"><p class="pr-eyebrow">CUSTOMER SUPPORT</p><h1>Review Moderation</h1><p>Check patient privacy, content, and photo permissions before approval.</p>
@forelse($reviews as $review)<article class="pr-detail pr-moderation-card"><span class="pr-category">{{ ucfirst($review->status) }}</span><h2 data-user-content>{{ $review->publicText($review->title ?: 'Patient Experience') }}</h2><p>{{ $review->procedure }} · {{ $review->masked_name }} · {{ $review->rating }} / 5</p><p data-user-content class="pr-full-text">{{ $review->publicText($review->body) }}</p>
@if($review->gallery->isNotEmpty())<p>Photo publication consent: <strong>{{ $review->photo_consent?'Granted':'Not granted — photos must remain private' }}</strong></p><div class="pr-results pr-photo-gallery">@foreach($review->gallery as $photo)<figure><img src="{{ $photo['moderation_url'] }}" alt="Patient photo for private moderation" loading="lazy"></figure>@endforeach</div>@endif
<form action="{{ route('reviews.moderate',$review) }}" method="POST" class="pr-review-form">@csrf<label>Moderation note<textarea name="note" maxlength="2000" rows="2">{{ $review->moderation_note }}</textarea></label><div class="pr-modal-footer"><button class="pr-modal-cancel" name="decision" value="rejected">Reject</button><button class="pr-modal-submit" name="decision" value="published">Approve and publish</button></div></form></article>@empty<div class="pr-empty">No patient reviews to moderate.</div>@endforelse
{{ $reviews->links() }}</section>
@endsection
