<section class="real-guest-request container" data-no-translate lang="en">
 <article class="real-guest-panel" id="my-bookings"><h2>My Bookings</h2>
 @forelse($customerBookings as $booking)
 <section class="real-guest-quote"><h3>{{ $booking->reference }} · {{ $booking->status }}</h3>
 <p>{{ $booking->treatmentRequest->procedure_label }} · {{ $booking->hospital?->name }}</p>
 <p>Treatment date: {{ $booking->appointment_date?->format('M j, Y') ?? 'Awaiting hospital confirmation' }}</p>
 @if($booking->deposit_phase==='treatment' && $booking->quotation)
 <x-requests.deposit-summary :deposit="(new \App\Services\DepositPayment)->treatmentSummary($booking)" :verification-amount="in_array($booking->deposit_status,['Pending','Pending Verification','Information Required'],true)?$booking->deposit_amount_satang:null" />
 @endif
 <a class="button button-outline" href="{{ route('guest.requests.show',$booking->treatmentRequest->reference) }}#booking-deposit">View Booking &amp; Payment</a>
 @if($booking->deposit_status==='Paid' && $booking->payment_verified_at && $booking->treatmentRequest->canChatWithHospital())<button type="button" class="button button-outline" data-customer-booking-chat>Booking Chat</button>@else<p class="real-guest-help">Booking Chat unlocks after deposit approval.</p>@endif
 </section>
 @empty<p>No bookings yet. Your requests and quotations remain available above.</p>@endforelse
 </article>
 <article class="real-guest-panel" id="payments"><h2>Payments</h2>
 @forelse($customerBookings as $booking)<section class="real-guest-quote">
 <h3>{{ $booking->reference }}</h3><p>Deposit: {{ $booking->deposit_amount_satang===null?'Not recorded':'THB '.number_format($booking->deposit_amount_satang/100,2) }} · {{ $booking->payment_status }}</p>
 <p>Approved: {{ $booking->payment_verified_at?->format('M j, Y H:i') ?? 'Not approved' }}</p>
 <a class="button button-outline" href="{{ route('guest.requests.show',$booking->treatmentRequest->reference) }}#booking-deposit">View Payment Details</a>
 </section>@empty<p>No deposits recorded.</p>@endforelse
 </article>
</section>
