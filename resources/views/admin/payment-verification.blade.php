<h3>Payment Verification Queue</h3>
<p class="ap-note">Payment Status is read-only. Confirm Payment records your independent verification of received funds.</p>
@php($canReview=!$booking->is_test && !$booking->payment_is_simulated && $booking->status==='Pending Payment' && !in_array($booking->deposit_status,['Paid','Refunded']))
@php($latestSubmission=$booking->paymentSubmissions->first())
@if($canReview && $paymentReviewReady)
<form method="POST" action="{{ route('admin.payments.confirm',$booking) }}" data-payment-confirm="Confirm you independently checked bank records and received the full deposit? This marks payment Paid and enables account activation.">@csrf
<input type="hidden" name="confirmation" value="1"><input type="hidden" name="funds_verified" value="1">
<button type="submit" class="ap-button gold" aria-label="Approve Payment">Confirm Payment</button>
</form>
<p class="ap-note">Confirm only after checking that the full deposit has arrived in the receiving bank account. No customer slip is required.</p>
@endif
@forelse($booking->paymentSubmissions as $submission)
<section class="ap-panel">
<h3>Submission #{{ $submission->id }} - {{ ucfirst(str_replace('_',' ',$submission->status)) }}</h3>
<dl class="request-contact-details">
<dt>Request ID</dt><dd>{{ sprintf('REQ-%s-%06d',$booking->treatmentRequest->created_at->format('Y'),$booking->treatment_request_id) }}</dd>
<dt>Deposit</dt><dd>{{ $money($submission->amount_satang) }}</dd><dt>Payment Method</dt><dd>{{ $submission->method }}</dd>
<dt>Transaction Reference</dt><dd>{{ $submission->transaction_reference ?? 'Not provided' }}</dd>
<dt>Submission Date</dt><dd>{{ $submission->method==='Not submitted'?'Not submitted':$submission->submitted_at->format('M j, Y H:i') }}</dd>
</dl>
@if($submission->proof_path)<a class="ap-button" href="{{ route('admin.payments.proof',$submission) }}">View Payment Proof</a><small>Download Private Payment Proof</small>@endif
@if($submission->status==='pending' && $canReview)
<form method="POST" action="{{ route('admin.payments.review',$submission) }}" data-payment-confirm="Confirm this payment review decision? Approval records received funds and enables account activation.">@csrf
<label>Review Notes / Rejection Reason<textarea name="notes" required maxlength="2000"></textarea></label>
<input type="hidden" name="confirmation" value="1">
<button class="ap-button" name="decision" value="rejected">Reject Payment</button>
</form>
@endif
<h4>Verification History</h4>
@forelse($submission->reviews as $review)<p>{{ $review->created_at->format('M j, Y H:i') }} - {{ $review->reviewer?->name ?? 'Former admin' }} - {{ ucfirst(str_replace('_',' ',$review->decision)) }}: {{ $review->notes }}</p>@empty<p class="ap-note">No reviews yet.</p>@endforelse
</section>
@empty<p class="ap-note">Verification History: No reviews yet.</p>@endforelse
