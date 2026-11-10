# Payment workflow test report

Date: 2026-10-28 (Asia/Bangkok)

## Current payment capability

The application supports **manual bank-transfer evidence review**, backed by Laravel database records and private file storage. It has no payment gateway SDK/configuration, gateway sandbox, bank API, signed payment webhook, or bank-generated payable QR integration. The QR asset is an example and cannot receive funds.

An uploaded receipt or customer click does not mark a booking Paid. Only an authorized Admin review can do that. Approval requires evidence, confirmation of independently verified received funds, a bank verification reference, and review notes. That confirmation is an operator assertion: the application does not independently contact a bank.

The deposit rule is **10% of the accepted quotation, minimum THB 2,000, maximum THB 5,000, capped at the quotation total**. The limits are documented exceptions to an exact 10% deposit, requested by the project owner. Examples: THB 40,000 => 4,000; 75,000 => 5,000; 10,000 => 2,000; 1,000 => 1,000. Existing saved deposit amounts are not silently repriced.

## Changes made during this test

- Restored first-submission evidence entry in a collapsed "Submit Payment Evidence" section. Previously, a new guest could submit evidence only after Admin requested it; the earlier UI removal prevented the direct browser journey.
- Changed payment API integration metadata to `manual_review`, with `available: false` and `bank_connected: false`. This identifies the real evidence workflow without suggesting a working gateway.
- Updated the public guide to describe evidence review, independent verification and the deposit limits, rather than implying that a working QR provider is available.
- Added an integration test that exercises the real guest, hospital quotation, evidence submission, Admin approval, OTP verification, account creation and dashboard routes.
- Added coverage for using one verified bank reference on different bookings, and real-flow OTP expiry, attempt limits and reuse prevention.

## Test environment and boundaries

- Laravel tests use SQLite `:memory:`, an isolated private storage fake, array cache/session, and fictional `example.test` recipients.
- The integration test uses the real Laravel notification/mail rendering pipeline with the local **array mail transport**, not Notification::fake. It extracts the random OTP from the rendered email and submits it through the verification route.
- Hospital quotations are submitted through the real hospital controller route, not inserted directly in the integration test.
- The persistent return test reuses the encrypted HttpOnly cookie actually issued by the submission response, clears the session, advances time two days and follows the resume route.
- The bank verification result is explicitly a TEST operator fixture. No transfer occurred and no bank confirmed it.
- No live customer records were approved, no real funds were charged, and no external emails were sent during the automated tests. The existing local SMTP configuration was inspected only for its transport and whether a host is configured; secrets were not printed or changed.

## Results

Laravel: **140 passed, 0 failed, 1,512 assertions**.

JavaScript: **59 passed, 0 failed**. This suite includes legacy mock-only guest-journey tests as well as current draft, access and deposit tests; mock tests are not evidence of bank connectivity.

| Scenario | Result and scope |
|---|---|
| Guest request -> hospital quote -> acceptance | Passed through real application routes |
| Deposit and remaining balance | Passed integer-satang calculation and configured exceptions |
| First evidence submission | Passed; private receipt and customer reference saved |
| Pending Verification | Passed; no automatic approval or OTP before approval |
| Payment queue and proof access | Passed; Admin can download proof; customer/hospital access denied |
| Independent verification requirement | Passed; missing confirmation/reference/evidence rejects approval |
| Admin evidence request | Passed; signed return-link notification and customer evidence form |
| Rejection and corrected evidence | Passed; reason displayed, previous records retained, same booking |
| Duplicate submission and approval | Passed; no duplicate booking/submission or activation email |
| Duplicate bank verification reference | Passed; normalized reference cannot approve another booking |
| Refresh and return after two days | Passed at HTTP/session/cookie level; same Pending Verification record |
| Cross-device access | Signed-link authorization tests passed; malformed/unauthorized access denied |
| Approval -> OTP -> account -> auto login | Passed, including actual local email rendering and random-code extraction |
| My Bookings | Passed; original approved booking visible in the new account |
| OTP expiry, attempt limits and reuse | Passed |
| Gateway sandbox and signed bank callbacks | Not executable: integration does not exist |
| Real email inbox delivery | Not tested; local array transport only |
| Native browser Back/Forward and visual layouts | Not verified by browser automation in this run; HTTP and JavaScript state tests only |

## Reproduce

```text
php artisan test --compact
php artisan test --compact --filter=PaymentJourneyIntegrationTest
node --test tests/js/*.test.mjs
npm.cmd run build
```

For a manual UI rehearsal on an isolated test deployment, use fictional users and test-only receipts. Submit a request, have a hospital enter a quotation, accept it, open Submit Payment Evidence, and submit a fictional reference/receipt. Inspect the queue as Admin, first test rejection or an evidence request, then test approval with an explicitly TEST verification reference. Use a local mail catcher to inspect the OTP and complete account setup. Do not approve fictional receipts in a database containing real financial records.

## Remaining work before accepting real payments

1. Establish the actual AESTHETIC receiving bank account and customer-facing transfer instructions. The current example QR must not be presented as payable.
2. For manual transfers, require operational bank-statement reconciliation by the authorized reviewer. An application checkbox is not a bank API verification.
3. For gateway testing, select a provider offering the required Thai payment method, supply sandbox merchant credentials securely, and implement server-created payment references and verified, replay-safe callbacks. Test failed, expired, refunded and duplicate callbacks before considering live mode.
4. Verify real SMTP delivery and resend/recovery behavior using an approved test mailbox, without exposing OTPs in logs.
5. Complete browser/mobile visual checks and a manual browser navigation rehearsal on the test deployment.

Live payment processing has not been enabled.
