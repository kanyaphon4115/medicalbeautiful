export const otpDigits = value => String(value || '').replace(/\D/g, '').slice(0, 6);
export function distributeOtp(fields, value, start = 0) {
    const digits = otpDigits(value);
    if (digits.length === 6) start = 0;
    [...digits].forEach((digit, i) => { if (fields[start + i]) fields[start + i].value = digit; });
    return Math.min(fields.length - 1, start + digits.length);
}

export function initializeGuestVerification(dialog) {
    if (!dialog) return;
    const fields = [...dialog.querySelectorAll('[data-otp-digit]')];
    const form = dialog.querySelector('[data-guest-otp-form]');
    const resend = dialog.querySelector('[data-guest-otp-resend]');
    const resendButton = resend.querySelector('button');
    const feedback = dialog.querySelector('[data-otp-feedback]');
    let countdown, busy = false;
    function open() { if (!dialog.open) dialog.showModal(); fields[0]?.focus(); }
    document.querySelector('[data-open-guest-otp]')?.addEventListener('click', open);
    dialog.querySelector('[data-close-guest-otp]').addEventListener('click', () => dialog.close());
    function cooldown(seconds) {
        clearInterval(countdown);
        const until = Date.now() + Math.max(0, Number(seconds) || 0) * 1000;
        const tick = () => {
            const left = Math.max(0, Math.ceil((until - Date.now()) / 1000));
            resendButton.disabled = left > 0 || busy;
            resendButton.textContent = left ? `Resend Code (${left}s)` : 'Resend Code';
            if (!left) clearInterval(countdown);
        };
        countdown = setInterval(tick, 1000); tick();
    }
    fields.forEach((field, index) => {
        field.addEventListener('input', event => {
            const value = otpDigits(event.data?.length > 1 ? event.data : field.value);
            if (value.length > 1) fields[distributeOtp(fields, value, index)]?.focus();
            else { field.value = value; if (value) fields[index + 1]?.focus(); }
        });
        field.addEventListener('paste', event => {
            event.preventDefault(); fields[distributeOtp(fields, event.clipboardData.getData('text'), index)]?.focus();
        });
        field.addEventListener('keydown', event => {
            if (event.key === 'Backspace' && !field.value) fields[index - 1]?.focus();
            if (event.key === 'ArrowLeft') fields[index - 1]?.focus();
            if (event.key === 'ArrowRight') fields[index + 1]?.focus();
        });
    });
    async function post(action, body) {
        const response = await fetch(action, {method: 'POST', credentials: 'same-origin', cache: 'no-store',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value}, body: JSON.stringify(body)});
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Unable to verify. Please try again.');
        return data;
    }
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (busy) return;
        const code = fields.map(field => field.value).join('');
        if (!/^[0-9]{6}$/.test(code)) { feedback.textContent = 'Enter all six digits.'; return; }
        busy = true; form.querySelector('button').disabled = true; feedback.textContent = 'Verifying...';
        try {
            const data = await post(form.action, {code}); feedback.textContent = data.message;
            await new Promise(resolve => setTimeout(resolve, 500)); window.location.assign(data.redirect);
        } catch (error) { feedback.textContent = error.message; fields[0]?.focus(); }
        finally { busy = false; form.querySelector('button').disabled = false; }
    });
    resend.addEventListener('submit', async event => {
        event.preventDefault(); if (busy || resendButton.disabled) return;
        busy = true; resendButton.disabled = true;
        try { const data = await post(resend.action, {}); feedback.textContent = data.message; busy = false; cooldown(data.cooldown); }
        catch (error) { feedback.textContent = error.message; busy = false; cooldown(60); }
    });
    cooldown(resendButton.dataset.cooldown);
    window.addEventListener('pagehide', () => clearInterval(countdown), {once: true});
    if (dialog.hasAttribute('data-auto-open')) open();
}
