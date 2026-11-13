@php
 $filters=$paymentFilters;
 $money=fn($value)=>$value===null?'Not recorded':'THB '.number_format($value/100,2);
 $paymentDate=fn($p)=>($p->reviewed_at ?? ($p->method!=='Not submitted'?$p->submitted_at:null))?->format('M j, Y H:i') ?? 'Not recorded';
@endphp
<div data-no-translate lang="en">
<section class="ap-panel">
 <nav class="ap-payment-stages" aria-label="Payment stage">
 @foreach(['all'=>'All Payments','initial'=>'Initial Deposit','treatment'=>'Booking Deposit'] as $stage=>$label)
 <a href="{{ route('admin.payments',array_merge(request()->except(['page','payment','legacy']),['stage'=>$stage])) }}" @if(($filters['stage']??'all')===$stage) aria-current="page" @endif>{{ $label }} <span>{{ $paymentStageCounts[$stage] }}</span></a>
 @endforeach
 </nav>
 <form method="GET" class="ap-filters">
  <input type="hidden" name="lang" value="en"><input type="hidden" name="stage" value="{{ $filters['stage']??'all' }}">
  @if($filters['record']??null)<input type="hidden" name="record" value="{{ $filters['record'] }}">@endif
  <label class="ap-search"><input name="search" type="search" value="{{ $filters['search']??'' }}" placeholder="Search by name, payment ID, booking ID or email..." aria-label="Search"></label>
  <select name="status" aria-label="Status"><option value="">All Status</option>@foreach(['Pending','Pending Verification','Rejected','Information Required','Paid','Failed','Expired','Refunded'] as $status)<option @selected(($filters['status']??'')===$status)>{{ $status }}</option>@endforeach</select>
  <label class="ap-date-label">Payment date from<input name="from" type="date" value="{{ $filters['from']??'' }}"></label><label class="ap-date-label">Payment date to<input name="to" type="date" value="{{ $filters['to']??'' }}"></label>
  <select name="size" aria-label="Page size">@foreach([5,10,25] as $size)<option value="{{ $size }}" @selected(($filters['size']??5)==$size)>{{ $size }} / page</option>@endforeach</select>
  <button class="ap-button gold">Apply filters</button><a class="ap-button" href="{{ route('admin.payments',['lang'=>'en']) }}">Clear</a>
 </form>
 @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
 @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
 <div class="ap-table-scroll"><table><thead><tr><th>Payment ID</th><th>Customer</th><th>Payment Stage</th><th>Hospital</th><th>Amount</th><th>Status</th><th>Payment Date</th><th>Actions</th></tr></thead><tbody>
 @forelse($paymentTransactions as $payment)
 @php($booking=$payment->booking)
 <tr>
  <td>{{ $payment->reference }} @if($booking->is_test)<span class="ap-badge gold">Test</span>@endif @if($booking->payment_is_simulated)<small>Simulated payment</small>@endif</td>
  <td>{{ $booking->treatmentRequest?->name ?: $booking->customer?->name ?: 'Name not provided' }}</td>
  <td>{{ $payment->stage_label }}</td><td>{{ $booking->hospital?->name ?: 'Not recorded' }}</td><td>{{ $money($payment->amount_satang) }}</td>
  <td><span class="ap-badge {{ $payment->status==='approved'?'green':'gold' }}">{{ $payment->display_status }}</span></td><td>{{ $paymentDate($payment) }}</td>
  <td><button type="button" class="ap-button" data-real-payment="{{ $payment->id>0?'tx-'.$payment->id:'legacy-'.$booking->id }}">View</button></td>
 </tr>
 @empty<tr><td colspan="8">No records found.</td></tr>@endforelse
 </tbody></table></div>
 <p class="ap-note">Showing {{ $paymentTransactions->firstItem()??0 }}&ndash;{{ $paymentTransactions->lastItem()??0 }} of {{ $paymentTransactions->total() }} payments. Date filters use recorded payment dates; undated pending payments are excluded.</p>
 {{ $paymentTransactions->withQueryString()->links() }}
</section>
@foreach($paymentTransactions as $payment)
 @php($booking=$payment->booking)
 <dialog class="real-request-dialog ap-stage-payment-dialog" id="real-payment-{{ $payment->id>0?'tx-'.$payment->id:'legacy-'.$booking->id }}" data-no-translate lang="en" aria-labelledby="payment-title-{{ $payment->id }}">
  <button type="button" class="ap-button" data-close-real-payment>Close</button><h2 id="payment-title-{{ $payment->id }}">Payment Details</h2>
  <dl class="request-contact-details">
   <dt>Payment ID</dt><dd>{{ $payment->reference }}</dd>
   <dt>Request ID</dt><dd>{{ sprintf('REQ-%s-%06d',$booking->treatmentRequest->created_at->format('Y'),$booking->treatment_request_id) }}</dd>
   <dt>Customer Name</dt><dd>{{ $booking->treatmentRequest->name ?: $booking->customer?->name }}</dd>
   <dt>Hospital</dt><dd>{{ $booking->hospital?->name ?? 'Not recorded' }}</dd>
   <dt>Payment Stage</dt><dd>{{ $payment->stage_label }}</dd>
   <dt>Payment Status</dt><dd><span class="ap-badge {{ $payment->status==='approved'?'green':'gold' }}">{{ $payment->display_status }}</span></dd>
  </dl>
  @if($payment->purpose==='treatment' && $booking->quotation)
   @php($deposit=(new \App\Services\DepositPayment)->treatmentSummary($booking))
   <x-requests.deposit-summary :deposit="$deposit" :admin="true" :verification-amount="in_array($payment->status,['pending','information_requested'],true)?$payment->amount_satang:null" />
   @if($deposit['initial_satang']<=0)<p role="alert">Initial Deposit must be approved before Booking Deposit can be confirmed.</p>@endif
  @elseif($payment->purpose==='initial')
   <section class="ap-payment-summary"><dl class="request-contact-details"><dt>Initial Deposit Amount</dt><dd>{{ $money($payment->amount_satang) }}</dd><dt class="ap-payment-verify-amount">Amount to Verify</dt><dd class="ap-payment-verify-amount">{{ $money($payment->amount_satang) }}</dd></dl></section>
  @endif
  @include('admin.payment-verification')
  <a class="ap-button" href="{{ route('admin.bookings',['record'=>$booking->id]) }}#real-payment-{{ $booking->id }}">View Booking Details</a>
  @if($booking->payment_is_simulated)<p class="ap-note">SIMULATED PAYMENT &mdash; recorded by a local test action. No real funds were received.</p>@endif
 </dialog>
@endforeach
</div>
