import {translateText} from './i18n.js';
export function depositAmounts(amount, dueNowSatang = 500000) {
    const total = Math.round(Number(amount) * 100);
    if (!Number.isSafeInteger(total) || total < 1) throw new Error('Invalid quotation amount.');
    const deposit = Math.min(total, 500000, Math.max(200000, Math.round(total / 10)));
    return {total_satang:total, deposit_satang:deposit, remaining_satang:total-deposit};
}

export function openDepositPayment({request, response, showModal, back, onAccepted=()=>{}}) {
    const money = satang => satang===null?'Not recorded':'THB ' + (satang/100).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
    const escape = value => String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const content = document.getElementById('modal-content');
    function show(title,html) {
        document.getElementById('demo-modal').classList.remove('booking-payment-dialog');
        content.dataset.noTranslate='';content.lang='en';showModal(title,`<div class="deposit-payment">${html}</div>`);
    }
    function amounts(data) {
        const balanceLabel=data.total_paid_satang===undefined?'Balance After This Payment':'Remaining Balance &mdash; Pay at Hospital';
        return `<dl class="deposit-breakdown"><div><dt>Total Quotation Price</dt><dd>${money(data.total_satang)}</dd></div><div class="deposit-highlight"><dt>${escape(translateText('Amount Due Now',document.documentElement.lang))}</dt><dd>${money(data.deposit_satang)}</dd></div><div><dt>${balanceLabel}</dt><dd>${money(data.remaining_satang)}</dd></div></dl>`;
    }
    async function api(url, options={}) {
        const result=await fetch(url,{credentials:'same-origin',cache:'no-store',...options,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}});
        let data={};try{data=await result.json();}catch{}
        if(!result.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Payment details could not be loaded. Please reload and try again.');
        return data;
    }
    function bookingPayment(data) {
        const deposit=data.treatment_deposit;
        const isPaid=data.payment_status==='Paid';
        const unavailable=!data.integration?.available;
        const row=(label,value,css='')=>`<div class="${css}"><dt>${label}</dt><dd>${escape(value)}</dd></div>`;
        show(isPaid?'Booking Deposit Paid':'Pay Booking Deposit',`<p class="booking-payment-subtitle">${isPaid?'Your booking deposit payment has been verified and approved.':'Scan the QR Code to make your booking deposit payment.'}</p>
            <div class="booking-payment-columns">
                <figure class="booking-payment-qr">${isPaid?'':`<div class="booking-payment-code"><img src="${escape(document.body.dataset.paymentQrExample)}" alt="Example QR code - not for payment"><i></i><i></i><i></i><i></i></div>`}<figcaption><span>${isPaid?'Booking Deposit Paid':'Amount to Pay'}</span><strong>${money(isPaid?deposit.booking_paid_satang:data.deposit_satang)}</strong></figcaption></figure>
                <section class="booking-payment-details" aria-label="Payment Details"><h3>Payment Details</h3><dl>
                    ${row('Request No.',data.request_reference)}${row('Treatment',data.procedure_name)}${row('Hospital',data.hospital_name||'Selected Hospital')}
                    ${row('Total Treatment Cost',money(deposit.total_satang))}${row('Required Deposit ('+deposit.percentage+'%)',money(deposit.required_satang))}
                    ${row('Initial Deposit Paid','- '+money(deposit.initial_satang),'booking-payment-deduction')}
                    ${row('Booking Deposit Paid (Step 9)',money(deposit.booking_paid_satang),'booking-payment-deduction')}
                    ${row('Total Deposit Paid',money(deposit.total_paid_satang))}
                    ${isPaid?'':row('Amount to Verify (not yet paid)',money(data.deposit_satang))}
                    ${row('Remaining Balance &mdash; Pay at Hospital',money(deposit.remaining_satang),'booking-payment-total')}
                </dl></section>
            </div>
            ${unavailable&&!isPaid?'<p class="booking-payment-unavailable">Example QR only. Live QR payment is not configured. Do not transfer using this image.</p>':''}
            <p class="deposit-hospital-note">Remaining amount to be paid directly to the hospital or clinic on the treatment date.</p>
            <p class="booking-payment-info"><span aria-hidden="true">&#9432;</span><span>${isPaid?'Payment approved. Please do not pay this deposit again.':'After payment, please wait for admin verification and approval.'}</span></p>`);
        const modal=document.getElementById('demo-modal');modal.classList.add('booking-payment-dialog');
        modal.addEventListener('close',()=>modal.classList.remove('booking-payment-dialog'),{once:true});
    }

    function payment(data) {
        if(data.purpose==='treatment'&&data.treatment_deposit){bookingPayment(data);return;}
        show(data.purpose==='treatment'?'Treatment Deposit (Step 9)':'Initial Deposit (Step 4)',`<p class="deposit-type">Thai QR Payment</p><figure class="payment-qr-example"><img src="${escape(document.body.dataset.paymentQrExample)}" alt="Example QR image for the deposit payment screen; not for payment" width="260" height="320"><figcaption>Example QR image · Not for payment</figcaption></figure><p class="deposit-notice">Payment integration is not configured. This placeholder cannot receive payments. Do not transfer money.</p><p class="deposit-reference">Booking Reference<br><strong>${escape(data.booking_reference)}</strong></p><p>${escape(data.procedure_name)}</p>${amounts(data)}${data.submission_url?`<a class="button button-gold" href="${escape(data.submission_url)}">View Payment / Submit Proof</a>`:''}<div class="deposit-state"><span>Payment Status</span><strong>${escape(data.payment_status)}</strong></div>${data.payment_is_simulated?'<p class="deposit-notice">Simulated test payment. No real funds received.</p>':''}<p class="deposit-booking-status">Booking Status: ${escape(data.booking_status)}</p><button type="button" class="button button-gold deposit-done">Close</button>`);
        content.querySelector('.deposit-done').onclick=()=>document.getElementById('demo-modal').close();
    }
    if(response.payment_url) {
        show('Deposit Payment','<p role="status">Loading payment details...</p>');
        const pendingPanel=content.querySelector('.deposit-payment');
        api(response.payment_url).then(data=>{if(pendingPanel.isConnected && document.getElementById('demo-modal').open)payment(data);}).catch(error=>{if(pendingPanel.isConnected)pendingPanel.textContent=error.message;});return;
    }
    const preview=depositAmounts(response.quotation_amount, Number(document.querySelector('[data-requests-page]').dataset.depositSatang));
    show('Confirm Your Booking',`<p class="deposit-procedure">${escape(request.procedure_name)}</p>${amounts(preview)}<p class="deposit-notice">After verified deposit payment, your booking awaits hospital confirmation of the treatment date.</p><p class="auth-form-error" id="deposit-error" role="alert" hidden></p><div class="deposit-confirm-actions"><button type="button" class="button button-outline" data-deposit-cancel>Cancel</button><button type="button" class="button button-gold" data-deposit-ok>OK</button></div>`);
    content.querySelector('[data-deposit-cancel]').onclick=back;
    content.querySelector('[data-deposit-ok]').onclick=async event=>{
        const button=event.currentTarget,error=content.querySelector('#deposit-error'),pendingPanel=content.querySelector('.deposit-payment');button.disabled=true;error.hidden=true;content.querySelector('[data-deposit-cancel]').disabled=true;
        try {const data=await api(response.accept_url,{method:'POST',body:JSON.stringify({expected_total_satang:preview.total_satang})});
            response.accept_url=null;response.payment_url=data.payment_url;onAccepted(data);
            if(!pendingPanel.isConnected || !document.getElementById('demo-modal').open)return;
            payment(data);
        } catch(failure){if(!pendingPanel.isConnected)return;error.textContent=failure.message;error.hidden=false;button.disabled=false;content.querySelector('[data-deposit-cancel]').disabled=false;}
    };
}
