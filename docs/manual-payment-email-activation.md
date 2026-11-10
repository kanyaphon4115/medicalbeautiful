# Manual payment confirmation and email activation

Updated: 2026-10-28 (Asia/Bangkok).

## Implemented workflow

1. Accepted quotations retain their saved booking and deposit. New deposits use 10%, minimum THB 2,000 and maximum THB 5,000, capped at the quotation total. The stable `PAY-year-id` reference identifies the booking payment record; it is not a bank transaction ID.
2. Guests submit a slip or bank reference. Submission remains Pending Verification.
3. Super Admin uses Confirm Payment, Reject Payment or Request New Payment Slip in the existing Payments view. Confirmation requires evidence, explicit confirmation, independent bank verification and a unique bank verification reference. Reviewer, date and notes are audited.
4. Confirm Payment sets Paid and Awaiting Hospital Confirmation. New guests receive a queued payment-confirmation email with the subject `Payment Confirmed – Verify Your Email | AESTHETIC`, a signed private return link and a 6-digit OTP.
5. The server stores only the OTP hash, booking/email binding, generation identifier, attempts and expiry. OTP lifetime is 10 minutes; resend cooldown is 60 seconds. Five incorrect attempts exhaust that generation, including after the general rate-limit window expires.
6. The Verify Your Email dialog has six individual inputs, focus movement, keyboard navigation, paste support, error/success feedback and resend cooldown. Verification uses the server. A non-JavaScript form remains available.
7. New guests are redirected to the protected Create Account route. Existing-email guests are redirected to Sign In and must authenticate before linking the booking.
8. Username is the existing verified email, shown read-only. Password, confirmation and terms are collected without repeating personal details. Account creation links the original request/booking/payment through the booking owner and automatically logs the customer in.

## Queue and email operations

- Real delivery uses the existing SMTP configuration and the `database` connection, queue **payment-mail**.
- `SendGuestActivationCode` is an encrypted queue job: the plaintext code is not readable in the jobs table. It retries three times with 30/60/120-second backoff and a 60-second timeout. SMTP error details are replaced by a generic exception before entering failure logs.
- Retries reuse the same code/generation. Already delivered, consumed, replaced, expired or ineligible generations are ignored. Repeat payment approvals are rejected before another notification is queued.
- As with normal SMTP, a process failure after provider acceptance but before the delivery marker can cause a repeat of the same email. It does not create another booking or activation flow. Absolute exactly-once delivery would require provider-level idempotency.
- Queued codes expire ten minutes after generation. The worker must be running promptly; an expired queued job will not deliver an unusable code. Guests can request a fresh code.
- A hidden local worker was started for this workspace on 2026-10-28 with a maximum runtime of 24 hours. This is not a persistent Windows service or a reboot-safe deployment.

For ongoing operation, supervise/restart this command through the deployment's normal process manager:

```text
php artisan queue:work database --queue=payment-mail --sleep=3 --tries=3 --timeout=60
```

Application code changes require the usual graceful worker restart and relaunch by that process manager. Logs for the temporary local worker are in `storage/logs/payment-mail-worker.log` and `storage/logs/payment-mail-worker-error.log`.

## Infrastructure inspection

- Existing payment-review tables, jobs and failed_jobs tables are present. No new migration was needed.
- SMTP is configured. No credentials were printed or changed.
- The payment-mail queue had no pending/failed jobs when the worker was started; no test email was sent to a real customer.
- Actual inbox delivery has not been tested. Automated tests use local array transport or notification fakes.
- The QR is still the existing example asset. A real receiving QR/account must be supplied before presenting it as a payable QR. Manual verification does not require a gateway, but the Admin must reconcile the actual receiving bank records.
- No reference screenshot was attached with this request. The dialog follows the existing white/cream/gold styling; exact screenshot matching and browser visual QA remain unverified.

## Verification

Coverage includes the complete guest/hospital/payment/account journey, actual local email rendering, encrypted queue payload, successful retry, duplicate/stale/expired/consumed job suppression, authorization, duplicate approvals, one-time code use, attempt limits, new-account JSON redirect and existing-account secure Sign In.

```text
php artisan test --compact
node --test tests/js/*.test.mjs
npm.cmd run build
```

No real payment was approved and no production email success was simulated during testing.
