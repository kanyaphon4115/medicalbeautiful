@if(isset($payment))
@php
 $canReview=!$booking->is_test && !$booking->payment_is_simulated && $booking->status==='Pending Payment'
  && $booking->treatmentRequest->status!=='cancelled' && $payment->purpose===$booking->deposit_phase
  && in_array($payment->status,['pending','information_requested'],true)
  && in_array($booking->deposit_status,['Pending','Pending Verification','Rejected','Information Required'],true)
  && $payment->amount_satang>0
  && ($payment->purpose==='initial' || $booking->paymentSubmissions->where('purpose','initial')->where('status','approved')->isNotEmpty());
@endphp
@if($canReview && $paymentReviewReady)
<form method="POST" action="{{ $payment->id>0 ? route('admin.payments.confirm-transaction',$payment) : route('admin.payments.confirm',$booking) }}" data-payment-confirm="Confirm you independently checked bank records and received {{ $money($payment->amount_satang) }} for {{ $payment->purpose==='treatment' ? 'Booking Deposit' : $payment->stage_label }}?">@csrf
 <input type="hidden" name="confirmation" value="1"><input type="hidden" name="funds_verified" value="1"><input type="hidden" name="payment_stage" value="{{ $payment->purpose }}">
 <button type="submit" class="ap-button gold" aria-label="Approve Payment">Confirm Payment</button>
</form>
@endif
@if($payment->id>0)
 @if($payment->proof_path)<a class="ap-button" href="{{ route('admin.payments.proof',$payment) }}">View Payment Proof</a><small>Download Private Payment Proof</small>@endif
 @if($payment->status==='pending' && $canReview && $booking->deposit_status==='Pending Verification')
 <form method="POST" action="{{ route('admin.payments.review',$payment) }}" data-payment-confirm="Reject this selected payment transaction?">@csrf
  <label>Review Notes / Rejection Reason<textarea name="notes" required maxlength="2000"></textarea></label><input type="hidden" name="confirmation" value="1">
  <button class="ap-button" name="decision" value="rejected">Reject Payment</button>
 </form>
 @endif
 <h3>Verification History</h3>
 @forelse($payment->reviews as $review)<p class="ap-note">{{ $review->created_at->format('M j, Y H:i') }} - {{ $review->reviewer?->name ?? 'Former admin' }} - {{ ucfirst(str_replace('_',' ',$review->decision)) }}: {{ $review->notes }}</p>@empty<p class="ap-note">No reviews yet.</p>@endforelse
@else
 <p class="ap-note">Legacy payment record. Recorded bank verification details are retained.</p>
@endif
@else
<p class="ap-note">Payment verification is temporarily unavailable.</p>
@endif
