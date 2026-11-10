@php
 $money=fn($value)=>$value===null?'Not recorded':'THB '.number_format($value/100,2);
 $filters=$paymentFilters;
 $isBooking=$page==='bookings';
 $paymentId=fn($b)=>sprintf('PAY-%s-%06d',$b->created_at->format('Y'),$b->id);
 $paymentDate=fn($b)=>$b->payment_verified_at?->format('M j, Y H:i') ?? 'Not recorded';
@endphp
<div data-no-translate lang="en">
<section class="ap-panel">
 <form method="GET" class="ap-filters">
  <input type="hidden" name="lang" value="en">
  <label class="ap-search"><input name="search" type="search" value="{{ $filters['search']??'' }}" placeholder="Search by name, ID or email..." aria-label="Search"></label>
  @if($isBooking)<label>Hospital<input name="hospital" value="{{ $filters['hospital']??'' }}"></label>@endif
  @if(!$isBooking)<select name="method" aria-label="Payment method"><option value="">All payment methods</option><option value="unrecorded" @selected(($filters['method']??'')==='unrecorded')>Not recorded</option></select>@endif
  <select name="status" aria-label="Status"><option value="">All Status</option>@foreach($page==='payments'?['Pending','Pending Verification','Rejected','Information Required','Paid','Failed','Expired','Refunded']:['Pending Payment','Awaiting Hospital Confirmation','Confirmed','Completed','Cancelled'] as $status)<option @selected(($filters['status']??'')===$status)>{{ $status }}</option>@endforeach</select>
  @if($isBooking)
  <label class="ap-date-label">Booking created from<input name="from" type="date" value="{{ $filters['from']??'' }}"></label><label class="ap-date-label">Booking created to<input name="to" type="date" value="{{ $filters['to']??'' }}"></label>
  @if($paymentLocal)<select name="records" aria-label="Record type">@foreach(['all'=>'All database records','real'=>'Real records only','test'=>'Test records only'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['records']??'all')===$value)>{{ $label }}</option>@endforeach</select>@endif
  <select name="size" aria-label="Page size">@foreach([5,10,25] as $size)<option value="{{ $size }}" @selected(($filters['size']??5)==$size)>{{ $size }} / page</option>@endforeach</select>
  @endif
  <button class="ap-button gold">Apply filters</button><a class="ap-button" href="{{ route('admin.'.$page,['lang'=>'en']) }}">Clear</a>
 </form>
 @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
 @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
 <div class="ap-table-scroll"><table><thead><tr>
 @if(!$isBooking)<th>Payment ID</th>@endif
 <th>Booking ID</th><th>Customer Name</th><th>Hospital</th>
 @if($isBooking)<th>Procedure</th><th>Preferred Treatment Date</th><th>Confirmed Treatment Date</th><th>Booking Status</th>
 @else<th>Total Procedure Price</th><th>Deposit Amount</th><th>Payment Method</th><th>Payment Status</th><th>Payment Date</th>@endif
 <th>Actions</th></tr></thead><tbody>
 @forelse($realBookings as $booking)<tr>
 @if(!$isBooking)<td>{{ $paymentId($booking) }}</td>@endif
 <td>{{ $booking->reference }} @if($booking->is_test)<span class="ap-badge gold">Test</span>@endif @if($booking->payment_is_simulated)<small>Simulated payment</small>@endif</td>
 <td>{{ $booking->treatmentRequest?->name ?: $booking->customer?->name ?: 'Name not provided' }}</td><td>{{ $booking->hospital?->name ?: 'Not recorded' }}</td>
 @if($isBooking)
 <td>{{ $booking->treatmentRequest?->procedure_label }}</td><td>{{ $booking->treatmentRequest?->preferred_surgery_date?->format('M j, Y') ?? $booking->treatmentRequest?->preferred_period_label ?? 'Not recorded' }}</td>
 <td>{{ $booking->appointment_date?->format('M j, Y') ?? 'Not confirmed' }}</td><td><span class="ap-badge gold">{{ $booking->status }}</span></td>
 @else
 <td>{{ $booking->quotation ? 'THB '.number_format($booking->quotation->amount,2) : 'Not recorded' }}</td><td>{{ $money($booking->deposit_amount_satang) }}</td><td>{{ $booking->paymentSubmissions->first()?->method ?? 'Not recorded' }}</td>
 <td><span class="ap-badge {{ $booking->deposit_status==='Paid'?'green':'gold' }}">{{ $booking->deposit_status }}</span></td><td>{{ $paymentDate($booking) }}</td>
 @endif
 <td><button type="button" class="ap-button" data-real-payment="{{ $booking->id }}">View</button></td>
 </tr>@empty<tr><td colspan="10">No records found.</td></tr>@endforelse
 </tbody></table></div>
 <p class="ap-note">Showing {{ $realBookings->firstItem()??0 }}?{{ $realBookings->lastItem()??0 }} of {{ $realBookings->total() }} records. Date filters use {{ $isBooking?'booking creation dates':'recorded payment verification dates; undated pending payments are excluded when filtering by date' }}.</p>
 {{ $realBookings->withQueryString()->links() }}
</section>
@foreach($realBookings as $booking)
<dialog class="real-request-dialog" id="real-payment-{{ $booking->id }}" data-no-translate lang="en"><button type="button" class="ap-button" data-close-real-payment>Close</button><h2>{{ $isBooking?'Booking Details':'Payment Details' }}</h2>
 <dl class="request-contact-details">
 @foreach(['Booking ID'=>$booking->reference,'Customer'=>$booking->treatmentRequest?->name ?? $booking->customer?->name,'Email'=>$booking->treatmentRequest?->email ?? $booking->customer?->email,'Hospital'=>$booking->hospital?->name,'Quotation Price'=>$booking->quotation ? 'THB '.number_format($booking->quotation->amount,2) : 'Not recorded','Deposit Amount'=>$money($booking->deposit_amount_satang),'Payment Status (read-only)'=>$booking->deposit_status] as $label=>$value)<dt>{{ $label }}</dt><dd>{{ $value ?? 'Not recorded' }}</dd>@endforeach
 @if($isBooking)
 <dt>Request ID</dt><dd>{{ $booking->treatmentRequest ? sprintf('REQ-%s-%06d',$booking->treatmentRequest->created_at->format('Y'),$booking->treatment_request_id) : 'Not recorded' }}</dd>
 <dt>Procedure</dt><dd>{{ $booking->treatmentRequest?->procedure_label }}</dd>
 <dt>Preferred Treatment Date</dt><dd>{{ $booking->treatmentRequest?->preferred_surgery_date?->format('M j, Y') ?? $booking->treatmentRequest?->preferred_period_label }}</dd>
 <dt>Confirmed Treatment Date</dt><dd>{{ $booking->appointment_date?->format('M j, Y') ?? 'Not confirmed' }}</dd>
 <dt>Hospital Confirmation Status</dt><dd>{{ in_array($booking->status,['Confirmed','Completed'])?'Confirmed':($booking->status==='Cancelled'?'Cancelled':'Awaiting hospital confirmation') }}</dd>
 <dt>Booking Status</dt><dd>{{ $booking->status }}</dd>
 @else
 <dt>Payment ID</dt><dd>{{ $paymentId($booking) }}</dd><dt>Payment Method</dt><dd>{{ $booking->paymentSubmissions->first()?->method ?? 'Not recorded' }}</dd>
 <dt>Transaction Reference</dt><dd>{{ $booking->paymentSubmissions->first()?->transaction_reference ?? 'Not recorded' }}</dd><dt>Payment Date and Time</dt><dd>{{ $paymentDate($booking) }}</dd>
 <dt>Refund Amount</dt><dd>{{ $money($booking->refunded_amount_satang) }}</dd>
 @endif
 </dl>
 @if(!$isBooking)@include('admin.payment-verification')@endif
 <a class="ap-button" href="{{ route('admin.'.($isBooking?'payments':'bookings'),['record'=>$booking->id]) }}#real-payment-{{ $booking->id }}">{{ $isBooking?'View Payment Details':'View Booking Details' }}</a>
 @if($isBooking)
 <h3>Booking history</h3><ul><li>Created: {{ $booking->created_at->format('M j, Y H:i') }}</li>@if($booking->payment_verified_at)<li>{{ $booking->payment_is_simulated?'Simulated':'Verified' }} payment: {{ $paymentDate($booking) }}</li>@endif<li>Last updated: {{ $booking->updated_at->format('M j, Y H:i') }} ? {{ $booking->status }}</li></ul>
 <p class="ap-note">Only retained events are shown. Earlier status changes were not recorded by the existing system.</p>
 @php($transitions=['Pending Payment'=>['Cancelled'],'Awaiting Hospital Confirmation'=>['Confirmed','Cancelled'],'Confirmed'=>['Completed','Cancelled']][$booking->status]??[])
 @if($transitions)<form method="POST" action="{{ route('admin.bookings.status',$booking) }}">@csrf
 <label>Booking status<select name="status">@foreach($transitions as $status)<option>{{ $status }}</option>@endforeach</select></label>
 <label>Confirmed treatment date<input type="date" name="appointment_date" value="{{ $booking->appointment_date?->format('Y-m-d') }}"></label><button class="ap-button gold">Update booking</button></form>@endif
 @endif
 @if($booking->payment_is_simulated)<p class="ap-note">SIMULATED PAYMENT — recorded by a local test action. No real funds were received.</p>@endif
</dialog>
@endforeach
</div>
