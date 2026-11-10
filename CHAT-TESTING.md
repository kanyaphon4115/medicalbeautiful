# Testing Customer Support with two accounts

- Use two separate Chrome profiles, Chrome plus Edge, or one normal window plus one Incognito window. Two normal tabs share the same login. All Incognito windows also share a login with each other.
- Sign into the customer account in the first profile and send a Customer Support message.
- Sign into the authorized support account in the second profile. Open the chat and select the customer in Support inbox.
- Reply from Support. Both sides refresh messages automatically while the chat is open, about two seconds after the previous request completes.
- Do not use Hospital for this test: its existing approval and payment checks remain enabled.
- Only users with the stored role admin can use Support inbox. New registrations always default to role user; the role is not mass assignable or accepted by registration. The existing admin account (ID 3) is authorized. TestZZ (ID 5) remains a normal user. A display name such as admin grants no access.
- Logging out or changing accounts in another tab invalidates the old chat screen; polling stops, sending is disabled, and the typed draft is retained. Open the page again after signing in.
- An additive migration adds users.role with default user. No users or messages are deleted. A private SQL backup was verified before applying the migration. Existing chat messages were checked unchanged afterward.