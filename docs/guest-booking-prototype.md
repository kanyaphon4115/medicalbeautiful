# MedicalBeautiful customer journey demo

Current notification behavior is documented in [guest-quotation-notifications.md](guest-quotation-notifications.md). The mounted homepage uses the frontend notification demo; historical live-mail opt-in behavior below is disabled in this mount.

The guest journey uses the existing homepage layout and hash navigation. It now follows the eight stages in the supplied reference: request form → hospital/quotation selection → QR deposit → payment confirmation → email preview → six-digit verification → account creation → dashboard.

## Changed files

- `resources/js/guest-journey.js`: complete screen/controller flow, smart form, local photo previews, hospital selection, demo QR/countdown, payment receipt, email preview, six OTP inputs, username/password/terms, customer sidebar, filters, editing and duplication.
- `resources/js/guest-journey-state.js`: reusable deposit calculation and transitions, expiring payment attempts, idempotent confirmation, username uniqueness, profile reuse and pending-only edits.
- `resources/css/guest-journey.css`: responsive white/cream/gold reference styling for the eight screens.
- `resources/views/components/guest-journey.blade.php`: existing homepage entry point, asset paths, and authenticated admin identity for the demo-only override.
- `tests/js/guest-journey*.test.mjs`, `tests/helpers/guest-dom.mjs`, and `tests/Feature/GuestJourneyPageTest.php`: state/controller/integration coverage.
- `public/build`: rebuilt Vite assets.

Hospital/doctor pages and real Laravel requests, quotations, bookings, payment/admin controls, login, Google Login and chat APIs are unchanged. No migrations or payment gateway were added.

## Start and test the entire journey

Open `http://localhost/medicalbeautiful/public/#journey/new` or the homepage's **Try Guest Request**.

1. Fill in fictional full name/email, LINE ID, international phone, date of birth and gender. Select at least one procedure, beauty goals, preferred treatment month/year and consent. Add local photos if desired (up to 10 JPG/PNG/WebP, 5 MB each). Additional Notes are independent per request. Photos are not uploaded or transmitted.
2. Submit to **Select a Hospital**. Two clearly fictional sample quotations are available. Switch hospital using the name tabs or scroll the quotation cards. View Details opens the selected quotation/request. Favorite heart toggles in this session. Additional fixture responses are available under Demo quotation controls.
3. **Proceed to Payment** opens the Thai QR / PromptPay style screen with a clearly non-scannable demo placeholder. It shows hospital, quotation total, deposit, balance, unique reference, and a 30-minute countdown.
4. **I have completed the payment** only reports payment and stays Waiting for Payment. Open **Demo payment provider** and click **Simulate verified payment** to simulate a separate provider confirmation. Only this creates a demo booking, marks its demo deposit Paid and issues the code. Repeating confirmation does not create another booking. No real money is transferred.
5. The successful payment receipt shows amount, method, reference, request and date. Continue to the simulated email preview with the six-digit code (ten-minute expiry). No real email is sent by this default demo flow.
6. Enter the six digits. Typing advances focus; Backspace moves back; pasting a complete code fills all boxes. Invalid, expired and five-attempt errors are handled. Resend has a 30-second cooldown; use Back to Email to see the new demo code.
7. Create an available username (3–30 characters, letter first), use an 8+ character demo-only password with upper/lowercase letters and a number, confirm it and accept the demo terms/privacy. The account is created only in tab-local state; it automatically opens the dashboard with the original request and paid booking attached.
8. Test **My Requests**, **My Quotations**, **My Chats**, **My Profile**, **Settings**, filters and Logout. Booking Chat is scoped to customer, selected hospital and support; Customer Support also works before booking.
9. Sign back in using the demo username or email and password. **New Request** pre-fills contact details including birth date and gender. Procedures/goals/month/year/photos/notes start fresh. A second paid booking does not repeat account activation. Existing-email guest checkout requires verification and sign-in rather than creating another account.
10. **Duplicate** pre-fills contact and treatment choices but never photos, IDs, quotations or payment history. It creates nothing until Submit. Each submitted request gets its own ID.
11. To test pending edits, expand **Demo response timing** in the form and choose **Start with a pending request** before submitting. With no quotations, View Request → Edit works. Once a quotation exists, edit is locked; Duplicate creates a new request for revised quotations. Old quotations/payments remain unchanged.
12. To test expiry, let the QR reference expire. Confirmation is blocked; **Generate new reference** creates a fresh attempt and retains expired attempt history in demo state.

## Deposit rule

New demo deposits use `min(total, 5000, max(2000, total * 0.05))`, rounded to two decimals. Examples: 40,000 → 2,000; 60,000 → 3,000; 80,000 → 4,000; 150,000 → 5,000. A quotation below 2,000 is capped at its total. The reference illustration's 65,000 quotation therefore calculates to **3,250**, following the explicit formula rather than its illustrative 3,000 label.

An authenticated real Admin browsing this demo sees **Admin: override demo deposit** within permitted limits before payment begins. The override records admin ID and timestamp in demo state. These are UI/model demo checks, not production authorization. Historical deposits are not recalculated. The real system's existing deposit rule is unchanged.

## Existing real-email feature

The prior Laravel signed access-link and real OTP endpoints remain intact. This new default demo never calls them. Previously emailed access links still work. The previous real-mail preview is explicitly opt-in at `#journey/new?email=real`; it sends real email, while all quotations/payments/accounts remain simulated. Its OTP remains server-generated, hashed, single-use and five-minute expiry. Do not test that route unless you intend to send an email. No SMTP credentials were changed or printed.

## Limits and production requirements

This is frontend-only demo state in sessionStorage (`aesthetic-guest-journey-v1`). Closing the tab or clearing storage may remove it. Photos stay in memory; only filenames are stored in request state. Use fictional medical/contact details and a unique demo-only password. The salted SHA-256 password verifier is a demonstration, not production password storage. Client-side roles, codes and payment events can be modified in developer tools and do not provide real security.

Production needs server-side guest ownership, real request persistence, private upload storage, strong password hashing, email verification and rate limits, secure sessions, role-based authorization and private chat/photo access controls. Dynamic payable QR must come from a configured payment gateway; confirm payments only via authenticated webhooks/status API with idempotency and audit logs. Never trust a customer click, browser state or email verification as payment evidence.

## Checks

`node --test tests/js/guest-journey.test.mjs tests/js/guest-journey-ui.test.mjs tests/js/guest-journey-email-ui.test.mjs`

`php artisan test --filter='GuestJourneyMailTest|GuestJourneyPageTest'`

`npm.cmd run build`

UI-controller tests use a minimal DOM adapter and are not pixel comparisons. Windows Computer Use could not initialize (`failed to start Node runtime`, OS error 3); a 100% visual match has not been verified in a browser.

## Customer access and How It Works update

The header now replaces public Sign Up with **How It Works**. Guests can still browse the homepage, procedure/hospital/doctor content and request form. **My Requests** displays a Sign In Required dialog with Sign In, How It Works and Cancel. The same guard applies to private demo routes entered directly, including dashboard, requests, quotations, chats, profile and settings. A scoped guest request status remains available through its existing guest-access flow before activation.

The responsive guide has six numbered, illustrated steps: Submit Request → Compare & Select Hospital → Pay Deposit via QR Code → Verify Your Email → Create Your Account → Customer Dashboard. Get a Free Quote opens the existing form without a login requirement. The modal explains that payment confirmation must precede email verification and labels this default journey as simulated.

After mock activation/sign-in, the header changes to a profile menu with My Dashboard, My Requests, My Quotations, My Chats, My Profile, Account Settings and Logout. Logout updates both the header and the in-memory journey authentication state. The smart form continues to pre-fill contact details and keep treatment choices independent.

Direct `/register` now redirects to the guide. Public email-registration POST is rejected with 403 and does not create a user. Existing Laravel sign-in, Google authentication, password recovery and authenticated admin/hospital features remain unchanged. Existing real account menus retain their server-authenticated handlers, and protected Laravel request/profile/settings/chat endpoints retain their auth middleware. Demo guards are browser-controlled and are not production authorization; apply server-side authorization to every future production customer endpoint and activation grant.

Changed: `resources/js/customer-access.js`, `resources/css/customer-access.css`, header/account/login Blade components, `resources/js/app.js`, `resources/js/guest-journey.js`, the registration route/controller, and access/auth regression tests.

Test guests by clicking My Requests and opening `#journey/dashboard`, `#journey/my-quotations`, `#journey/profile`, `#journey/settings` directly. Confirm that only the sign-in gate is shown. Open How It Works on desktop/mobile and use Get a Free Quote. Complete the existing simulated payment/code/account journey, check the new header menu, then logout and repeat a protected URL. No real verification email is sent by the default demo.
