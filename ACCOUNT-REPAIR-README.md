# Run On Console account repair — SOURCE ONLY

## Status and stop condition

This is a source overlay for the uploaded **GameForgeHub.zip**, not an upload-ready website or a verified production release. Do not extract it directly into public_html. Do not replace auth.php alone. The frontend, backend and schema must be tested and released together.

Completed checks: all 64 source JS/JSX files parse with the supplied Babel parser; static contract assertions pass. Not completed: PHP lint/runtime, MySQL integration, frontend production build, browser interactions, Gmail delivery, Google OAuth end-to-end. The build fails because the uploaded Windows node_modules lacks Linux Rollup. Dependency installation was not authorized in this environment. PHP runtime installation was also blocked. No live website, hosting configuration or production database was changed.

## Included changes

- Signup, resend, password reset and code/link verification use one consistent challenge implementation, account-bound code checking, five attempts, expiry, transactional consumption and rate limits.
- Password login and successful verification establish the same database-backed session expected by profile.php. Login accepts username or email. Initial frontend session loading no longer prematurely redirects the profile page.
- Verification links display a confirmation button; a GET/email preview does not consume them.
- Signup/resend/reset attempt SMTP immediately after committing the queue row and releasing the PHP session lock. Cron is the retry fallback. SMTP acceptance does NOT prove inbox delivery. Normal immediate delivery can still be delayed by the host relay/Gmail. SMTP timeouts are per operation, not an overall response deadline.
- Verification queues a welcome email with a normal /profile/ link; it is not a magic login link. Welcome emails are processed by Cron so profile navigation is not blocked by SMTP.
- Google callback checks state, nonce, signature, audience, issuer and expiry. A matching existing email is never silently merged: sign in with password first, then Link Google within ten minutes, using the same verified email.
- Editable private gaming profile: display name, picture/built-in avatar, bio, city/country, optional private DOB, games, gaming IDs and HTTPS social URLs. Username stays read-only. This is an owner-only profile editor, not a public profile-directory feature.
- Photo uploads are resized in-browser and decoded/re-encoded using PHP GD. Without GD, use built-in avatars.

## Apply to source, then validate

1. Keep an untouched copy of GameForgeHub.zip and a separate backup of actual live files and database outside public_html. Live files may differ from the uploaded source: compare before replacing.
2. Extract GameForgeHub.zip on your development computer. Overlay the src/, public/api/v1/ and scripts/ files from this package at the project root. These paths intentionally include `public/` because they are SOURCE paths, not cPanel deployment paths.
3. On a machine where dependency installation is permitted, run `npm ci`, then `node scripts/check-account-repair.cjs`, `npm run build`, and `npm run verify-seo`. Preserve the lockfile. Do not copy Windows node_modules to Linux.
4. Test with PHP 8.2+, PDO MySQL, cURL, OpenSSL and GD. Lint all changed PHP files with `php -l FILE` before execution. Run the migration and account tests on an isolated copy of the database first. The migration assumes the legacy base schema in the supplied source; it does not rebuild missing base tables or repair arbitrary schema drift.
5. This overlay depends on the original project's `public/api/v1/cron/PHPMailer/loader.php` and its complete sibling PHPMailer files. Keep those existing files. Never replace working private `/home2/runoncon/config/env.php` with example values.

## Schema and deployment — owner approval required

The explicit CLI migration is `public/api/v1/cron/migrate-account-flow.php`. It adds missing email_verified/is_admin flags, missing queue fields, and three new InnoDB tables: roc_auth_challenges, roc_auth_limits, roc_gaming_profiles. It does not delete existing users. DDL is not transactionally reversible: back up first. The migration does not replace legacy base tables.

When staging tests pass and the owner approves a maintenance window:

1. Pause signup/login traffic and the email worker during the coordinated release. Back up database and active frontend/backend files outside the web root.
2. Have the host run the reviewed migration with the correct PHP binary and private configuration. The deployed path would be:

   `/usr/local/bin/php /home2/runoncon/public_html/api/v1/cron/migrate-account-flow.php --apply`

3. Deploy the matching built frontend and API files together. Build output `dist/api/v1/` maps to `/home2/runoncon/public_html/api/v1/`; do not create another nested `api/v1/api/v1/` directory. The files under this package's src/ are not directly executable by the live website.
4. Review the generated build contents: do not upload database.sql, backups, ZIPs, logs, private configuration, source tools or node_modules. Do not blindly upload all of the original public/ folder. Do not change .htaccess, existing SEO pages, sitemaps or redirects as part of this account repair without separate review. Ensure changed frontend asset references and chunks are deployed consistently.
5. Keep ONE regular Cron entry (once per minute):

   `/usr/local/bin/php /home2/runoncon/public_html/api/v1/cron/process-email-queue.php >> /home2/runoncon/email-worker.log 2>&1`

   Confirm the host's actual PHP binary first. Remove any temporary SMTP test Cron. Do not add repeated overlapping sub-minute workers.
6. Resume traffic only after smoke tests pass. Request fresh verification/reset emails: old pre-release tokens are deliberately unsupported by the new handlers. Do not reset the entire email queue or mark all users verified.

Rollback: pause traffic/worker, restore the matching previous frontend and backend together. Added tables/columns may remain unused; do not drop them casually. A database restore can discard new registrations/profile updates, so restore only under an agreed maintenance/data-reconciliation plan. New-format links are invalid on the old backend too; issue fresh recovery links after rollback.

## Google setup still needs live confirmation

Keep secrets private. Confirm GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and GOOGLE_REDIRECT_URI in the existing private configuration. The configured callback and the OAuth client's authorized redirect URI must exactly match:

`https://runonconsole.com/api/v1/oauth/google-callback.php`

Confirm the consent-screen publishing/test-user restrictions. New Google users get a generated username and their profile opens after login. Existing password users must explicitly link Google. Google-only users can use Forgot Password to set a password before using the existing password-confirmed account deletion/email-change forms. A direct recent-Google-reauthentication deletion flow is not implemented here.

## Required acceptance tests — record results before production

- Fresh signup, immediate queued SMTP attempt, external inbox receipt; measure queue created_at/sent_at and Gmail receipt separately.
- Correct code opens authenticated /profile/; refresh stays signed in. Wrong/expired/reused codes fail. Cross-account code use fails. Five failures lock the challenge. Resend invalidates the old code and link.
- Open a verification link without clicking confirmation: account remains unverified; explicit confirmation succeeds once.
- Username/password and email/password login; logout; missing/invalid CSRF rejected; anonymous profile JSON returns 401.
- Password reset newest link works once, old links fail, all previous database sessions revoked.
- Fresh Google signup; subsequent Google login; cancel consent; wrong state/nonce/audience/signature rejected; same-email password account cannot be silently linked; explicit linking works.
- Save/reload avatar/photo, bio, games, IDs, city/country/DOB. Invalid image and javascript: social URL rejected. Another account cannot access this private data. Export includes gaming data; deletion removes new profile/challenge rows.
- Two workers cannot claim one pending row concurrently; interrupted jobs recover after 15 minutes; retries are bounded and delayed. SMTP is at-least-once: a crash after server acceptance can result in a duplicate email. This is not an exactly-once delivery guarantee.
- Mobile/desktop signup, verification and profile navigation; one header/footer; no duplicated homepage. Real unknown URLs retain HTTP 404. This patch does not claim to resolve server rewrite/prerender duplication or redesign animations.

Never send passwords, private keys, database exports, complete verification links or codes in support screenshots. Use the safe request reference ID and private log timestamp instead.
