<div class="hd-table-wrap"><table class="hd-real-table hd-patient-table"><thead><tr>
    @foreach(['Request ID','Procedure','Preferred Date','Customer','Status','Actions'] as $label)<th>{{ __($label) }}</th>@endforeach
</tr></thead><tbody>
@forelse($requests as $item)
<tr><td>{{ $reference($item) }}</td>
    <td><div class="hd-procedure"><img src="{{ asset('images/'.($images[$item->procedure] ?? 'other').'.jpg') }}" alt="{{ $item->procedure_label }}"><div><strong>{{ $item->procedure_label }}</strong><small>{{ \Illuminate\Support\Str::limit($item->goals ?: __('Not specified'),100) }}</small></div></div></td>
    <td>{{ $item->preferred_period_label }}</td>
    <td>{{ trim((string) $item->name) ?: (trim((string) $item->customer?->name) ?: 'Name not provided') }}</td>
    <td><span class="hd-badge {{ $item->quotations->isNotEmpty()?'green':'yellow' }}">{{ __($item->selected_hospital_id ? ($item->booking?->status ?? 'Pending Payment') : ($item->quotations->isNotEmpty() ? 'Quotation Sent' : 'New Request')) }}</span></td>
    <td><button type="button" class="hd-view" data-hd-request="{{ $item->id }}">{{ __('View Details') }}</button></td>
</tr>
@empty<tr><td colspan="6" class="hd-empty">{{ __('No patient requests available.') }}</td></tr>@endforelse
</tbody></table></div>
