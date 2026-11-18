<dialog class="pr-write-dialog" id="write-review-dialog" aria-labelledby="write-review-title" data-review-dialog @if($errors->any() || request()->routeIs('reviews.create')) data-auto-open @endif>
 <header class="pr-modal-header"><p>A’ AESTHETIC</p><h2 id="write-review-title">Write a Review</h2><span>Share your treatment experience with other patients.</span><button type="button" data-review-close class="pr-modal-close" aria-label="Close review form">×</button></header>
 <form method="POST" action="{{ route('reviews.store') }}" enctype="multipart/form-data" class="pr-modal-form" data-review-form>@csrf
  <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
  <div class="pr-modal-feedback" role="status" tabindex="-1" data-review-feedback>@if($errors->any()){{ $errors->first() }}@endif</div>
  @if(!$reviewPatient)<p class="pr-modal-eligibility">Please <a href="{{ route('login') }}">sign in with a verified patient account</a> to write a review.</p>@endif
  <fieldset class="pr-modal-rating"><legend>Overall Rating <b>*</b></legend><div class="pr-star-row">@for($i=1;$i<=5;$i++)<label title="{{ $i }} stars"><input type="radio" name="rating" value="{{ $i }}" required @checked(old('rating',5)==$i)><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 1.8 3.15 6.38 7.05 1.03-5.1 4.97 1.2 7.02L12 17.88l-6.3 3.32 1.2-7.02-5.1-4.97 7.05-1.03Z"/></svg><span class="sr-only">{{ $i }} out of 5 stars</span></label>@endfor<output data-review-rating>5.0 / 5</output></div></fieldset>
  <div class="pr-modal-field"><label for="review-body">{{ __('Write a Review') }}</label><textarea id="review-body" name="body" maxlength="5000" placeholder="{{ __('Write your review (optional)') }}">{{ old('body') }}</textarea></div>
  <fieldset class="pr-modal-photos"><legend>Photos <span>(Optional)</span></legend>
   <div class="pr-upload-layout pr-upload-general"><div class="pr-upload"><label for="review-photos"><svg viewBox="0 0 32 28" aria-hidden="true"><rect x="2" y="2" width="28" height="24" rx="3"/><circle cx="10" cy="9" r="2"/><path d="m3 23 9-10 6 5 6-8 6 8"/></svg><span>+ Add Photos</span><input id="review-photos" type="file" accept="image/jpeg,image/png" multiple data-review-photos data-upload-url="{{ route('reviews.upload') }}"></label></div>
   <div class="pr-upload-requirements"><h3>Requirements</h3><ul><li>JPG or PNG (Max 10 MB per photo)</li><li>Add as many photos as you like</li><li>You must have permission to share</li></ul></div></div>
   <div class="pr-upload-gallery" data-review-photo-gallery aria-label="Selected photos"></div>
  </fieldset>
  <div class="pr-approval-notice"><x-icon name="shield" size="40"/><div><h3>Public Review</h3><p>Your review will be visible to other visitors immediately after submission. Your name stays protected.</p><label class="pr-modal-check"><input type="checkbox" name="privacy_consent" value="1" required @checked(old('privacy_consent'))>I consent to public display of my review and any attached photos, with my name protected.</label></div></div>
  <footer class="pr-modal-footer"><button type="button" data-review-close class="pr-modal-cancel">Cancel</button><button type="submit" class="pr-modal-submit">Submit Review <x-icon name="arrow" size="22"/></button></footer>
 </form>
</dialog>
