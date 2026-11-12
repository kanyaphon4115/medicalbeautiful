<article class="beauty-request">
    <div class="request-procedure-image"><img data-procedure-image alt="" loading="lazy"><span data-request-id></span></div>
    <div class="request-summary"><div class="request-title-row"><h2 data-procedure-name></h2><x-requests.status-badge /></div><dl class="request-meta"><div><x-icon name="document" size="18"/><dt class="sr-only">{{ __('Submission date') }}</dt><dd data-submitted></dd></div><div><x-icon name="document" size="18"/><dt class="sr-only">{{ __('Hospitals contacted') }}</dt><dd data-contacted></dd></div><div><x-icon name="users" size="18"/><dt class="sr-only">{{ __('Preferred treatment date') }}</dt><dd data-preferred></dd></div></dl><div class="request-progress-heading"><span>{{ __('Hospital quotations') }}</span><span data-quotation-count></span></div><div class="request-progress" role="progressbar" aria-label="Hospital quotations received" aria-valuemin="0" aria-valuemax="100"><span></span></div></div>
    <div class="request-responses"><div class="request-no-responses"><svg width="38" height="38" viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="5" y="12" width="26" height="21" rx="2"/><path d="M13 12V7a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v5M18 18v9m-4.5-4.5h9"/></svg><p>{{ __('No hospital has responded yet.') }}</p><small>{{ __('Your request is saved.') }}<br>{{ __('Quotations will appear here when received.') }}</small></div><div class="request-response-content" hidden><h3>{{ __('Hospitals that responded (') }}<span data-response-count></span>)</h3><div class="request-hospital-grid"></div></div></div>
    <button class="request-details" data-view-request type="button"><span class="request-round-arrow"><x-icon name="arrow" size="23"/></span><span>{{ __('View Details') }}</span></button>
    <section class="treatment-deposit" data-treatment-deposit hidden>
        <div class="treatment-deposit-summary"><h3>Treatment Deposit (Step 9)</h3>
            <dl><div><dt>Total Treatment Cost</dt><dd data-treatment-total></dd></div>
            <div><dt>Required Deposit (<span data-treatment-percentage></span>%)</dt><dd data-treatment-required></dd></div>
            <div class="initial-deposit"><dt>Initial Deposit Paid</dt><dd data-treatment-initial></dd></div>
            <div class="paid-deposit"><dt>Booking Deposit Paid (Step 9)</dt><dd data-treatment-booking-paid></dd></div>
            <div><dt>Total Deposit Paid</dt><dd data-treatment-total-paid></dd></div>
            <div class="additional-deposit"><dt>Additional Deposit Required</dt><dd data-treatment-additional></dd></div>
            <div class="hospital-balance"><dt>Remaining Balance &mdash; Pay at Hospital</dt><dd data-treatment-remaining></dd></div></dl>
            <p class="deposit-hospital-note">Remaining amount to be paid directly to the hospital or clinic on the treatment date.</p>
            <button type="button" class="button button-gold" data-pay-treatment>Pay Booking Deposit</button><p data-treatment-error role="alert" hidden></p>
        </div>
        <p class="treatment-deposit-info"><span aria-hidden="true">?</span><span>Once payment is verified by our admin, the status will be changed to <strong>Confirmed.</strong></span></p>
    </section>
</article>
