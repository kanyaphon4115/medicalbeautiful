@extends('layouts.app')
@section('title','Your Treatment Request — AESTHETIC')
@section('content')
<section class="real-guest-request container" data-real-guest-page data-no-translate lang="en">
 <a class="real-guest-help" href="{{ route('home',['browse'=>1]) }}">Back to website</a>
 <p class="eyebrow">YOUR PRIVATE REQUEST</p><h1>{{ $progress['status']==='pending_verification'?'Payment Under Review':($progress['step']==='account'?'Complete Your Account':($progress['step']==='verification'?'Payment Approved':'Your Treatment Request')) }}</h1>
 <p class="real-guest-reference">{{ sprintf('REQ-%s-%06d',$item->created_at->format('Y'),$item->id) }} · {{ $item->procedure_label }}</p>
 @if($errors->any())<div class="real-guest-error" role="alert">{{ $errors->first() }}</div>@endif
 <div class="real-guest-grid"><article class="real-guest-panel"><h2>Request Details</h2>
 <dl><dt>Full Name</dt><dd>{{ $item->name }}</dd><dt>Email</dt><dd>{{ $item->email }}</dd><dt>LINE ID</dt><dd>{{ $item->line_id ?: 'Not provided' }}</dd><dt>Phone</dt><dd>{{ $item->phone_number ?: 'Not provided' }}</dd><dt>Date of Birth</dt><dd>{{ $item->date_of_birth ?: 'Not provided' }}</dd><dt>Gender</dt><dd>{{ $item->gender ?: 'Not provided' }}</dd><dt>Preferred Treatment</dt><dd>{{ $item->preferred_period_label }}</dd><dt>Status</dt><dd>{{ ['pending'=>'Pending','quotes_received'=>'Quotes Received','pending_payment'=>'Pending Payment','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled'][$item->customer_status] ?? $item->customer_status }}</dd></dl>
 <h3>Your Beauty Goals</h3><p class="real-guest-text">{{ $item->goals }}</p><h3>Medical Information</h3><p class="real-guest-text">{{ $item->medical_information ?: 'Not provided' }}</p>
 @if($item->additional_notes)<h3>Additional Notes</h3><p class="real-guest-text">{{ $item->additional_notes }}</p>@endif
 <h3>Private Photos</h3><div class="real-guest-photos">@forelse($item->photos as $photo)<a href="{{ route('guest.requests.photo',[$item->reference,$photo]) }}" target="_blank" rel="noopener noreferrer"><img src="{{ route('guest.requests.photo',[$item->reference,$photo]) }}" alt="Private treatment photo" loading="lazy"></a>@empty<p>No photos uploaded.</p>@endforelse</div>
 <p class="real-guest-message">We will email you automatically when a hospital submits or updates a quotation. Use the View Quotation button in the email to return to this request. You do not need an account to compare quotations or choose a hospital.</p><p class="real-guest-help">Keep your private email link to return to this request. Each email link expires in 7 days.</p>
 </article><div>
 @php($booking=$item->booking)
 @php($paidBalance=$booking?(new \App\Services\DepositPayment)->paidSummary($booking):null)
 @if($booking)<article class="real-guest-panel real-guest-payment" id="booking-deposit" data-payment-state="{{ $progress['status'] }}"><h2>{{ $progress['status']==='pending_verification'?'Payment Under Review':($progress['status']==='paid'?'Payment Approved':'Booking & Deposit') }}</h2><dl><dt>Procedure</dt><dd>{{ $item->procedure_label }}</dd><dt>Booking ID</dt><dd>{{ $booking->reference }}</dd><dt>Payment Reference</dt><dd>{{ $booking->payment_reference }}</dd><dt>Selected Hospital</dt><dd>{{ $booking->hospital?->name }}</dd><dt>Total Quotation Price</dt><dd>THB {{ number_format((float)$booking->quotation->amount,2) }}</dd><dt>Deposit Amount</dt><dd>{{ $booking->deposit_amount_satang===null?'Not recorded':'THB '.number_format($booking->deposit_amount_satang/100,2) }}</dd><dt>Remaining Balance &mdash; Pay at Hospital</dt><dd>THB {{ number_format($paidBalance['remaining_satang']/100,2) }}</dd><dt>Booking Status</dt><dd>{{ $booking->status }}</dd><dt>Payment Status</dt><dd>{{ $progress['status']==='pending_verification'?'Pending Admin Approval':$booking->payment_status }}</dd><dt>Request ID</dt><dd>{{ sprintf('REQ-%s-%06d',$item->created_at->format('Y'),$item->id) }}</dd><dt>Customer</dt><dd>{{ $item->name }}</dd><dt>Submission Date</dt><dd>{{ $booking->paymentSubmissions->first()?->method==='Not submitted'?'Not submitted':($booking->paymentSubmissions->first()?->submitted_at?->format('M j, Y H:i') ?? 'Not submitted') }}</dd></dl>
 @if($booking->deposit_phase==='treatment')
 <x-requests.deposit-summary :deposit="(new \App\Services\DepositPayment)->treatmentSummary($booking)" :verification-amount="in_array($booking->deposit_status,['Pending','Pending Verification','Information Required'],true)?$booking->deposit_amount_satang:null" />
 @endif
 @if($progress['status']==='pending_verification')
 <p class="real-guest-message">Your payment has been submitted and is awaiting verification by our team. You can safely leave this page and return later. Please do not make another payment.</p>
 <a class="button button-outline" href="{{ route('guest.requests.show',$item->reference) }}#booking-deposit">Check Payment Status</a>
 <button type="button" class="button button-outline" data-guest-support>Contact Support</button>
 @elseif(in_array($booking->deposit_status,['Failed','Expired','Refunded']))
 <p class="real-guest-message">Payment status: {{ $booking->deposit_status }}. Contact support before attempting another transfer.</p><a class="button button-outline" href="{{ route('guest.requests.show',$item->reference) }}#booking-deposit">Check Latest Status Before Retry</a><button type="button" class="button button-outline" data-guest-support>Contact Support</button><p class="real-guest-help">A new payable QR cannot be issued until the payment provider is connected. Your existing booking is retained.</p>
 @endif
 @if(in_array($progress['status'],['not_started','pending_payment','rejected','information_required']))
 <figure class="payment-qr-example"><img src="{{ asset('images/payment-qr-example.svg') }}" alt="Example QR image for the deposit payment screen; not for payment" width="260" height="320"></figure>
 @php($submission=$booking->paymentSubmissions->first())
 @if(in_array($progress['status'],['rejected','information_required']))<h3>{{ $progress['status']==='rejected'?'Payment Verification Unsuccessful':'Additional Payment Information Required' }}</h3><p class="real-guest-message">{{ $submission?->review_notes }}</p><p>Please contact our team if needed, then submit your payment notification below. Do not transfer money again unless our team asks you to.</p>@endif
 @if($paymentReviewReady && in_array($progress['status'],['not_started','pending_payment','rejected','information_required']))
 <form method="POST" enctype="multipart/form-data" action="{{ route('guest.requests.payment-claim',$item->reference) }}">@csrf
 <label>Transaction reference (optional)<input type="text" name="transaction_reference" maxlength="200"></label>
 <label>Payment proof (optional)<input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
 <button class="button button-gold">I Have Paid</button>
 </form>
 <p class="real-guest-help">No upload required. Our team will check received funds before approving your payment. The example QR cannot receive funds.</p>
 @endif
 @unless($paymentReviewReady)<p class="real-guest-message">Payment submissions are temporarily unavailable. Your request and booking are saved.</p>@endunless
 @if($booking->deposit_phase==='initial')<p class="real-guest-message">After your deposit payment is confirmed, a 6-digit OTP will be sent to your email address. Use it to verify your email and complete your account setup. You can then log in to access your requests, quotations, and other customer menus.</p>@endif @endif
 @php($paid=$booking->deposit_status==='Paid' && !$booking->is_test && !$booking->payment_is_simulated && $booking->payment_verified_at && $item->payment_confirmed_at)
 @if($paid && !$item->user_id)
 @if(!$progress['verified_email'])@include('requests.guest-verification')
 @else<section id="guest-account">
 <h3>{{ \App\Models\User::where('email',$item->email)->exists() ? 'Sign in to your existing account' : 'Create Your Account' }}</h3>
 <dl><dt>Full Name</dt><dd>{{ $item->name }}</dd><dt>Verified Email Address</dt><dd>{{ $item->email }}</dd><dt>Phone Number</dt><dd>{{ $item->phone_number ?: 'Not provided' }}</dd></dl>
 @include('requests.guest-account-form')
 </section>@endif
 @elseif($item->user_id && auth()->id()===$item->user_id)<a class="button button-gold" href="{{ route('requests.index') }}">View My Requests</a>@endif
 <form method="POST" action="{{ route('guest.requests.resend',$item->reference) }}">@csrf<button class="button button-outline">Email My Private Return Link</button></form>
 </article>@endif
 <article class="real-guest-panel"><h2>Hospital Quotations</h2><p>{{ $item->quotations->count() }} quotation(s) received</p>
 @forelse($item->quotations as $quotation)<section class="real-guest-quote"><h3>{{ $quotation->hospital?->name ?: 'Partner Hospital' }}</h3><p>{{ $item->procedure_label }}</p><strong class="real-guest-price">THB {{ number_format((float)$quotation->amount,2) }}</strong><p class="real-guest-text">{{ $quotation->details }}</p><small>Valid Until: {{ $quotation->valid_until?->format('M j, Y') }}</small>
 @if(!$booking && $item->status!=='cancelled' && $quotation->hospital?->isHospital() && (!$quotation->valid_until || !$quotation->valid_until->endOfDay()->isPast()))
 @php([$whole,$fraction]=explode('.',$quotation->amount))
 @php($total=(int)$whole*100+(int)$fraction)
 @php($deposit=\App\Services\DepositPayment::calculate($total))
 <p>Deposit Amount: <strong>THB {{ number_format($deposit/100,2) }}</strong></p><p>Remaining Balance: THB {{ number_format(($total-$deposit)/100,2) }}</p>
 <form method="POST" action="{{ route('guest.requests.accept',[$item->reference,$quotation]) }}">@csrf<input type="hidden" name="expected_total_satang" value="{{ $total }}"><button type="submit" class="button button-gold">Accept Quotation &amp; Continue</button></form>
 @elseif($booking?->quotation_id===$quotation->id)<p class="real-guest-message">Selected Quotation</p>@endif</section>
 @empty<p class="real-guest-message">@if($hasWithdrawnQuotations)The previous quotation has been withdrawn by the hospital. No active quotations are available right now. Please wait for a new quotation.@else No hospital has responded yet. Your request is saved and available to partner hospitals. Return using your private email link to check for quotations.@endif</p>@endforelse
 </article></div></div>
</section>
@endsection
