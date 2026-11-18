@extends('layouts.app')
@section('title','Hospital Verification | AESTHETIC')
@section('content')
@if(session('status'))<p class="container" role="status">{{ __(session('status')) }}</p>@endif
@if($errors->any())<ul class="container" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<section class="container" style="padding-block:40px"><h1>{{ __('Hospital Verification') }}</h1><p>{{ __('Verification details are private and available only to the hospital and Customer Support.') }}</p>
@forelse($changes as $change)<article class="hp-profile-card" style="padding:24px;margin-block:20px;border:1px solid #e7d9c5;border-radius:16px">
 <h2 data-user-content>{{ $change->profile->name?:$change->profile->hospital->name }}</h2><p>{{ __($change->status) }}</p>
 <dl class="request-contact-details"><dt>{{ __('Registered Hospital Name') }}</dt><dd data-user-content>{{ $change->payload['registered_name']?:__('Not recorded') }}</dd><dt>{{ __('Hospital License Number') }}</dt><dd data-user-content>{{ $change->payload['license_number']?:__('Not recorded') }}</dd></dl>
 <h3>{{ __('License Documents') }}</h3>@forelse($change->payload['documents'] as $document)<p><a data-user-content href="{{ $change->profile->mediaUrl('verification',$document['id']) }}">{{ $document['name'] }}</a></p>@empty<p>{{ __('No documents uploaded.') }}</p>@endforelse
 @if($change->status==='pending')<form method="POST" action="{{ route('hospital.verifications.decide',$change) }}">@csrf<label>{{ __('Review Notes') }}<textarea name="note" required maxlength="2000" rows="3" style="display:block;width:100%;border:1px solid #d8c3a5;border-radius:8px;padding:12px"></textarea></label><div style="display:flex;gap:12px;margin-top:15px"><button class="button button-outline" name="decision" value="rejected">{{ __('Reject') }}</button><button class="button button-gold" name="decision" value="approved">{{ __('Approve') }}</button></div></form>@else<p data-user-content>{{ $change->review_note }}</p>@endif
</article>@empty<p>{{ __('No verification requests.') }}</p>@endforelse
{{ $changes->links() }}</section>
@endsection
