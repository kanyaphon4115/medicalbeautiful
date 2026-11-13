@props(['deposit','verificationAmount'=>null,'admin'=>false])
@php($formatDepositMoney=fn($value)=>'THB '.number_format($value/100,2))
<section class="booking-deposit-summary {{ $admin?'ap-payment-summary':'' }}">
 <h3>Deposit Summary</h3>
 <dl class="request-contact-details">
  <dt>Total Treatment Cost</dt><dd>{{ $formatDepositMoney($deposit['total_satang']) }}</dd>
  <dt>Required Deposit Percentage</dt><dd>{{ $deposit['percentage'] }}%</dd>
  <dt>Required Treatment Deposit</dt><dd>{{ $formatDepositMoney($deposit['required_satang']) }}</dd>
  <dt>Initial Deposit Paid </dt><dd class="deposit-paid">{{ $formatDepositMoney($deposit['initial_satang']) }}</dd>
  <dt>Booking Deposit Paid </dt><dd class="deposit-paid">{{ $formatDepositMoney($deposit['booking_paid_satang']) }}</dd>
  <dt class="deposit-total-paid">Total Deposit Paid</dt><dd class="deposit-total-paid">{{ $formatDepositMoney($deposit['total_paid_satang']) }}</dd>
  @if($verificationAmount!==null)
  <dt class="ap-payment-verify-amount">Amount to Verify (not yet paid)</dt><dd class="ap-payment-verify-amount">{{ $formatDepositMoney($verificationAmount) }}</dd>
  @endif
  <dt class="deposit-remaining">Remaining Balance &mdash; Pay at Hospital</dt><dd class="deposit-remaining">{{ $formatDepositMoney($deposit['remaining_satang']) }}</dd>
 </dl>
 <p class="deposit-hospital-note">Remaining amount to be paid directly to the hospital or clinic on the treatment date.</p>
 @if($admin && $deposit['initial_satang']>0)<p class="ap-badge green">Initial Deposit &mdash; Approved</p>@endif
</section>
