<!DOCTYPE html><html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ __('Hospital Profile') }} | AESTHETIC</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css','resources/css/hospital.css','resources/css/hospital-profile.css','resources/css/responsive.css','resources/js/hospital-profile.js'])</head>
<body class="hospital-dashboard" data-dashboard-url="{{ route('hospital.dashboard') }}">
@include('hospital.partials.workspace-sidebar')
<div class="hd-shell">@include('hospital.partials.workspace-header')<main class="hp-workspace">
<div class="hp-page-heading"><p class="eyebrow">{{ __('HOSPITAL WORKSPACE') }}</p><h1>{{ __('Hospital Profile') }}</h1><p>{{ __('Manage your hospital information and photos.') }}</p></div>
@if(session('status'))<p class="hp-notice" role="status">{{ __(session('status')) }}</p>@endif
@if($errors->any())<ul class="hp-errors" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
@php($editing=request()->boolean('edit')||$errors->any())
<section class="hp-profile-card" data-profile-summary @if($editing) hidden @endif>
 <div class="hp-card-toolbar"><h2>{{ __('Hospital Profile') }}</h2><a class="button button-gold" href="{{ route('hospital.profile',['edit'=>1]) }}" data-profile-edit>{{ __('Edit Profile') }}</a></div>
 <div class="hp-profile-overview"><div class="hp-main-image" style="position:relative">@if($profile?->main_path)<img src="{{ $profile->mediaUrl('main') }}" alt="Hospital profile image">@elseif($profile?->gallery)<img src="{{ $profile->mediaUrl('gallery',$profile->gallery[0]['id']) }}" alt="Hospital profile image">@else<img src="{{ asset('images/hospital-1.jpg') }}" alt="{{ __('Sample hospital image') }}" style="min-height:310px;object-fit:cover"><span style="position:absolute;bottom:14px;left:14px;background:rgba(255,253,249,.94);padding:6px 12px;border-radius:20px;font-size:12px">{{ __('Sample image') }}</span>@endif</div>
 <div class="hp-profile-description">@if($profile?->logo_path)<img class="hp-logo" src="{{ $profile->mediaUrl('logo') }}" alt="Hospital logo">@endif<h2 data-user-content>{{ $profile?->name?:auth()->user()->name }}</h2><p data-user-content>{{ $profile?->short_description }}</p><dl>
 @foreach(['Phone Number'=>$profile?->phone?:auth()->user()->phone_number,'Email Address'=>$profile?->email?:auth()->user()->email,'Website'=>$profile?->website,'Hospital Address'=>$profile?->address,'Operating Hours'=>$profile?->operating_hours] as $label=>$value)<dt>{{ __($label) }}</dt><dd data-user-content>{{ $value?:__('Not recorded') }}</dd>@endforeach</dl>
 </div></div>
 @if($profile?->gallery)<div class="hp-copy"><h3>{{ __('Hospital Gallery') }}</h3><div class="hp-gallery">@foreach($profile->gallery as $photo)<img src="{{ $profile->mediaUrl('gallery',$photo['id']) }}" alt="Hospital gallery photo" loading="lazy">@endforeach</div></div>@endif
</section>
<form class="hp-edit-form" method="POST" action="{{ route('hospital.profile.update') }}" enctype="multipart/form-data" data-profile-form @if(!$editing) hidden @endif>@csrf
<section class="hp-profile-card"><h2>{{ __('Hospital Information') }}</h2><div class="hp-field-grid">
 @foreach(['name'=>['Hospital Name',$profile?->name?:auth()->user()->name,true],'city'=>['City / Province',$profile?->city,false],'country'=>['Country',$profile?->country?:auth()->user()->country_code,true],'maps_url'=>['Google Maps Location',$profile?->maps_url,false]] as $field=>[$label,$value,$required])<label>{{ __($label) }} @if($required)<b>*</b>@endif<input name="{{ $field }}" value="{{ old($field,$value) }}" type="{{ $field==='maps_url'?'url':'text' }}" maxlength="255" @required($required)></label>@endforeach
 <label class="hp-wide">{{ __('Short Description') }}<textarea name="short_description" rows="2" maxlength="500">{{ old('short_description',$profile?->short_description) }}</textarea></label>
 <label class="hp-wide">{{ __('Hospital Address') }} <b>*</b><textarea name="address" rows="2" maxlength="2000" required>{{ old('address',$profile?->address) }}</textarea></label>
</div></section>
<section class="hp-profile-card"><h2>{{ __('Contact Information') }}</h2><div class="hp-field-grid">
 @foreach(['phone'=>['Phone Number',$profile?->phone?:auth()->user()->phone_number,'tel',true],'email'=>['Email Address',$profile?->email?:auth()->user()->email,'email',true],'website'=>['Website',$profile?->website,'url',false],'operating_hours'=>['Operating Hours',$profile?->operating_hours,'text',false]] as $field=>[$label,$value,$type,$required])<label>{{ __($label) }} @if($required)<b>*</b>@endif<input name="{{ $field }}" value="{{ old($field,$value) }}" type="{{ $type }}" maxlength="{{ $field==='phone'?40:255 }}" @required($required)></label>@endforeach
 <p class="hp-help hp-wide">{{ __('Contact email changes do not change your sign-in email.') }}</p>
</div></section>
<section class="hp-profile-card"><h2>{{ __('Hospital Photos & Gallery') }}</h2><p class="hp-help">{{ __('JPG or PNG, up to 5 MB per image.') }}</p><div class="hp-field-grid">
 <div class="hp-wide"><label>{{ __('Gallery Photos') }}<input type="file" name="gallery[]" accept="image/jpeg,image/png" multiple data-profile-photo></label><div class="hp-upload-preview" data-file-preview></div><div class="hp-gallery">@foreach($profile?->gallery??[] as $photo)<figure><img src="{{ $profile->mediaUrl('gallery',$photo['id']) }}" alt="Hospital gallery photo"><label class="hp-check"><input type="checkbox" name="remove_gallery[]" value="{{ $photo['id'] }}">{{ __('Remove') }}</label></figure>@endforeach</div></div>
</div></section>
<div class="hp-form-actions"><a class="button button-outline" href="{{ route('hospital.profile') }}">{{ __('Cancel') }}</a><button class="button button-gold" type="submit">{{ __('Save Changes') }}</button></div>
</form></main></div></body></html>

