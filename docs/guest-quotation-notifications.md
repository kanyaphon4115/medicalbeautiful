# Guest quotation notification demo

The homepage guest journey uses frontend-only notification simulation (`data-notification-demo="true"`). It does not submit requests to Laravel, call email endpoints, send real emails, upload photos, or connect to a payment gateway. Existing backend features remain separate. Even the historical `?email=real` option is ignored by this mounted demo.

## Try it

1. Start a guest request using fictional information. To test the first response manually, select **Start with a pending request** under Demo response timing. Otherwise two sample hospital quotations generate two simulated notifications immediately.
2. Open **Demo email previews**. This tester mailbox shows simulated recipients in this tab. Under **Simulate hospital responses**, create a quotation or general hospital message. Replaying a response creates no duplicate notification.
3. Select **Preview Email**. The preview includes the request email, Request ID, generic notification text and **View Quotation**. Photos, treatment descriptions, goals, medical information and quotation details are excluded.
4. **View Quotation** opens `#journey/guest-link/{request-token}/{link-token}`. Each link belongs to one request and expires after seven days. Guests can read hospital responses, compare quotations, select a hospital and proceed without registering.
5. Continue through the existing Thai QR simulation. The deposit is 5% of the quotation, normally THB 2,000–5,000 and capped at the quotation total. Reporting a payment does not confirm it. **Simulate verified payment** confirms the demo deposit and generates a six-digit email code.
6. Preview and verify the code. New customers set up a demo account and are automatically signed in. Existing email/account holders sign in instead of creating another account.
7. The demo dashboard displays quotation notifications. **Disable email alerts** disables future simulated emails for that account while dashboard notifications continue. Responses are deduplicated by request, hospital and response event ID, including after state reload.

## Expired links

Invalid, revoked and expired links show the same replacement form without revealing request details. Enter Request ID and request email. Every submission returns the same confirmation, whether the combination matches or not. Matching requests receive a new simulated email; earlier links are revoked. Replacements have a silent 60-second cooldown. Retrieve the new link in the tester mailbox.

## Limits

State and tokens live in sessionStorage in the current browser tab. These are simulated authorization checks, not production security. Links require the matching demo state and do not work across browsers. Use fictional personal and medical information. No new backend, mail transport, migration, or payment integration was added for this feature.

## Checks

```text
node --test tests/js/guest-notifications.test.mjs tests/js/guest-notifications-ui.test.mjs tests/js/guest-journey.test.mjs tests/js/guest-journey-ui.test.mjs
php artisan test --filter=GuestJourneyPageTest
npm.cmd run build
```

Tests cover deduplication, multiple hospitals, safe preview contents, request scoping, expired and revoked links, generic replacement responses, optional alerts, payment before verification, automatic login, and zero network calls from the mounted demo.
