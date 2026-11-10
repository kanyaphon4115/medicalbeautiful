{{-- The public guest form submits to Laravel; booking/payment pages use saved records. --}}
@php
 $guestReference=auth()->guest()?app(\App\Services\GuestBookingResume::class)->reference(request()):null;
 $guestResume=$guestReference?route('guest.booking.resume'):null;
@endphp
<section data-guest-journey
         data-real-request-store="{{ route('guest.requests.store') }}"
         data-customer-requests-url="{{ route('requests.index') }}"
         @if($guestResume) data-real-resume-url="{{ $guestResume }}" @endif
         data-assets="{{ asset('images') }}"
         data-no-translate lang="en" hidden aria-label="Guest treatment request"></section>
