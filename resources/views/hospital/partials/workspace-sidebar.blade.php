@php
 $menus=['Dashboard'=>'home','Patient Requests'=>'document','My Quotations'=>'document','Bookings'=>'calendar','Booking Chat'=>'chat','Notifications'=>'bell','Hospital Profile'=>'hospital','Settings'=>'settings'];
 $workspaceProfile=$profile??\App\Models\HospitalProfile::where('hospital_id',auth()->id())->first();
 $workspaceName=$workspaceProfile?->name?:auth()->user()->name;
@endphp
<aside class="hd-sidebar" id="hospital-sidebar">
    <a class="brand" href="{{ route('home') }}"><span class="brand-mark">A</span><span><strong>AESTHETIC</strong><small>BEAUTY FOR A BETTER YOU</small><small class="brand-sample">{{ __('Sample logo') }}</small></span></a>
    <div class="hd-clinic">@if($workspaceProfile?->logo_path)<img src="{{ $workspaceProfile->mediaUrl('logo') }}" alt="Hospital logo">@elseif($workspaceProfile?->gallery)<img src="{{ $workspaceProfile->mediaUrl('gallery',$workspaceProfile->gallery[0]['id']) }}" alt="Hospital image">@else<img src="{{ asset('images/hospital-1.jpg') }}" alt="{{ __('Sample hospital image') }}" title="{{ __('Sample image') }}">@endif<strong data-user-content>{{ $workspaceName }}</strong><span>{{ __('Hospital Admin') }}</span></div>
    <nav aria-label="{{ __('Hospital navigation') }}">
        @foreach($menus as $label=>$icon)
        @if($label==='Hospital Profile')<a class="hd-profile-nav" href="{{ route('hospital.profile') }}" @if(request()->routeIs('hospital.profile')) aria-current="page" @endif><span class="hd-icon" data-hd-icon="{{ $icon }}"></span>{{ __($label) }}</a>
        @else<button type="button" data-hd-section="{{ $label }}" @if($loop->first && !request()->routeIs('hospital.profile')) aria-current="page" @endif><span class="hd-icon" data-hd-icon="{{ $icon }}"></span>{{ __($label) }}</button>@endif
        @endforeach
    </nav>
    <div class="hd-help"><x-icon name="headset"/><div><strong>{{ __('Need Help?') }}</strong><p>{{ __('Contact Platform Support') }}</p></div><button type="button" data-hd-support class="button button-outline">{{ __('Contact Support') }}</button></div>
</aside>
