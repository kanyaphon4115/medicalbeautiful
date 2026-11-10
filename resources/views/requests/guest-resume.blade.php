@extends('layouts.app')
@section('title','Continue Your Request — AESTHETIC')
@section('content')
<section class="real-guest-request container" data-no-translate lang="en">
 <p class="eyebrow">YOUR SAVED REQUESTS</p><h1>Continue Your Request</h1>
 <p>Choose the request you want to continue. Each keeps its own booking and payment history.</p>
 @foreach($items as $item)<article class="real-guest-panel">
 <h2>{{ sprintf('REQ-%s-%06d',$item->created_at->format('Y'),$item->id) }}</h2>
 <p>{{ $item->procedure_label }} · {{ $item->booking?->payment_status ?? 'Waiting for hospital quotations' }}</p>
 <a class="button button-gold" href="{{ route('guest.booking.resume',['request'=>$item->reference]) }}">Continue Your Request</a>
 </article>@endforeach
 <a class="button button-outline" href="{{ route('home',['browse'=>1]) }}">Back to Website</a>
</section>
@endsection
