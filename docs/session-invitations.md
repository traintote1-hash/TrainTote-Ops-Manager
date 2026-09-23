# Operations session invitations

The railroad owner can open **Invite Crew by Email or QR Code** from a session. Crew join for free with their name and a password-free invitation. They receive access to that session only, never an owner account. A personal email link works once and grants access immediately. A shared QR link can admit multiple people, but each join waits for owner approval. The owner can assign a participant to one assignment for progress updates, remove a participant, or revoke the entire invitation. Access also stops when the invitation expires or the session ends.

## Database setup

Apply `database/migrations/20260923_add_session_invitations.sql` to the same MySQL database used by the Operations app, **after** the existing Operations migrations. It creates only `operation_session_invites` and `operation_session_participants`. No changes to `users`, `railroads`, owner signup, or existing Operations tables are needed. Both tables use InnoDB so joins and owner changes can be checked in transactions. You can paste the SQL into phpMyAdmin with the application database selected, then verify both tables exist there. Apply the migration before uploading the new PHP pages.

## Email setup

The server must support PHP `mail()` and have a valid sender address in the `TT_OPS_MAIL_FROM` environment variable, for example an address on the TrainTote domain. Set this in the hosting environment; do not commit it in source control. The owner page says whether the mail server accepted the message. That does not guarantee delivery. If sending is unavailable, the page shows the personal invitation link once so the owner can copy and email it manually. QR creation does not require mail configuration.

The invitation URL is fixed to `https://ops.traintote.com/operations/join.php`, so the production host must serve the new pages at that address over HTTPS. The one-time link and QR are bearer credentials: share them only with intended crew and revoke any exposed invitation. The QR code is generated in the browser from a vendored, MIT-licensed library; it is not sent to a third-party QR service.

## Deployment check

After the migration and files are in place, create a test session invitation as an owner. Open a personal link in a separate browser session, join, and confirm that only the owner's session is visible. Open a QR link separately and confirm access waits for owner approval. Assign that QR participant to an assignment and confirm progress updates work only on that assignment while the session is active. Revoke the invitation and confirm crew access ends. No payment or paid-tier enforcement is included in this change.
