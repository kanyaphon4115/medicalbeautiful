<section class="ap-panel" data-no-translate lang="en">
    <div class="ap-panel-title"><h2>Customer Requests</h2><span>{{ $realRequests->total() }} requests</span></div>
    <div class="ap-table-scroll"><table><thead><tr><th>Request ID</th><th>Customer</th><th>Selected Procedures</th><th>Preferred Month / Year</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @forelse($realRequests as $item)<tr>
        <td>{{ sprintf('REQ-%s-%06d',$item->created_at->format('Y'),$item->id) }}</td>
        <td>{{ trim((string)$item->name) ?: ($item->customer?->name ?: 'Name not provided') }}</td>
        <td>{{ $item->procedure_label }}</td><td>{{ $item->preferred_period_label }}</td><td>{{ ['pending'=>'Pending','quotes_received'=>'Quotes Received','cancelled'=>'Cancelled','pending_payment'=>'Pending Payment','confirmed'=>'Confirmed','completed'=>'Completed'][$item->customer_status] ?? $item->customer_status }}</td>
        <td><button class="ap-button" type="button" data-real-request="{{ $item->id }}">View Details</button></td>
    </tr>@empty<tr><td colspan="6">No customer requests yet.</td></tr>@endforelse
    </tbody></table></div>
    {{ $realRequests->withQueryString()->links() }}
</section>
@foreach($realRequests as $item)
<dialog class="real-request-dialog" id="real-request-{{ $item->id }}" data-no-translate lang="en"><button type="button" class="ap-button" data-close-real-request>Close</button><h2>Request Details</h2>@include('requests.partials.details')
@include('admin.request-quotations')
</dialog>
@endforeach
