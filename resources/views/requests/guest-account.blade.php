@extends('layouts.app')
@section('title','Create Your Account — AESTHETIC')
@section('content')
<section class="real-guest-request container" data-no-translate lang="en">
 <article class="real-guest-panel guest-create-account">
 @php($existingAccount=\App\Models\User::where('email',$item->email)->first())
 <p class="eyebrow">EMAIL VERIFIED SUCCESSFULLY</p><h1>{{ $existingAccount?'Connect Your Existing Account':'Create Your Account' }}</h1>
 @if($existingAccount)
 <p>Your verified booking email is <strong>{{ $item->email }}</strong>. This email already belongs to an existing account{{ $existingAccount->isAdmin()?' (Super Admin)':($existingAccount->isHospital()?' (Hospital Admin)':'') }}. A second account cannot be created with the same email.</p>
 <p>Sign in using this email to connect your booking. If you intended to test a new customer, use a request with an email that has no existing account.</p>
 @endif
 <p>{{ $item->name }}, your request, booking and approved payment will be linked automatically.</p>
 @if($errors->any())<p class="real-guest-error" role="alert">{{ $errors->first() }}</p>@endif
 @include('requests.guest-account-form')
 </article>
</section>
@endsection
