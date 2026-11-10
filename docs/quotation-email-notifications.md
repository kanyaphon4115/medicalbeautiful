# Real quotation email notifications

Saving a hospital quotation through `hospital.quote` now sends a real SMTP notification to the request's email address after the database transaction succeeds. The homepage notification demo remains a separate simulation.

The email contains the Request ID and a **View Quotation** button. It excludes customer names, quotation details, procedure descriptions, medical information and photos. Guests receive a signed, request-specific link valid for seven days and can compare/select hospitals without registering. Registered request owners are directed to their authenticated requests dashboard; their quotation email preference is respected.

A cache lock serializes sends per quotation. A SHA-256 fingerprint of the saved amount, decrypted quotation details and expiry date prevents identical saves from sending duplicate notifications. Changes and responses from other hospitals notify separately. Successful SMTP acceptance records the fingerprint and delivery time on the quotation.

If sending fails, the quotation remains saved. The hospital sees a generic failure message and can save the unchanged quotation again to retry. Failed sends are not marked as delivered. No retrospective emails are sent by the migration. No queue worker is required.

SMTP configuration is reused; credentials are not modified. Automated tests fake notifications and verify destination, privacy, signed-link expiry, deduplication, updates, multiple hospitals, failure/retry behavior and account preferences. SMTP acceptance does not establish final inbox delivery; no live email was sent as a verification step.
