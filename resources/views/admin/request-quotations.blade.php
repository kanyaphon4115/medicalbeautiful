<section class="request-record-details" data-no-translate lang="en">
    <h3>Hospital Quotations</h3>
    @forelse($item->quotations as $quotation)
    <article class="ap-panel">
        <dl>
            <dt>Quotation ID</dt><dd>{{ $quotation->id }}</dd>
            <dt>Hospital</dt><dd>{{ $quotation->hospital?->name ?? 'Not recorded' }}</dd>
            <dt>Quotation Price</dt><dd>{{ $quotation->currency }} {{ number_format($quotation->amount,2) }}</dd>
            <dt>Valid Until</dt><dd>{{ $quotation->valid_until?->format('M j, Y') ?? 'Not specified' }}</dd>
            <dt>Status</dt><dd>{{ $item->booking?->quotation_id===$quotation->id && $item->booking->status!=='Awaiting Acceptance' ? 'Selected' : ($quotation->valid_until && $quotation->valid_until->copy()->endOfDay()->isPast() ? 'Expired' : 'Quote Received') }}</dd>
        </dl>
        <h3>Quotation Details</h3><p class="request-sensitive-text">{{ $quotation->details }}</p>
    </article>
    @empty<p>No hospital quotations received yet.</p>@endforelse
</section>
