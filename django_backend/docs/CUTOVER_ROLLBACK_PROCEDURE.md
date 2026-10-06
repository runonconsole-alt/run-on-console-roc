# Run On Console (ROC) - Cutover & Rollback Procedure

This document outlines the zero-downtime cutover and instant rollback procedure when transitioning the React frontend from the legacy `/api/v1/` PHP backend to the parallel `/api/v2/` Django backend.

---

## 🚦 1. Pre-Cutover Verification Checklist

Before switching endpoints in React, confirm all 7 conditions:

1. [ ] HosterPK support confirms Python 3.10+ and Passenger WSGI support.
2. [ ] `/api/v2/health/` returns `HTTP 200 OK` (`status: healthy`).
3. [ ] All 257 automated frontend/SEO test assertions pass.
4. [ ] Test signup on `/api/v2/auth/signup/` validates Turnstile server-side and dispatches email via HosterPK SMTP (Port 587 STARTTLS).
5. [ ] Verification 6-digit code and link work cleanly.
6. [ ] Session cookies carry `HttpOnly`, `Secure`, and `SameSite=Lax`.
7. [ ] cPanel Cron `process_email_queue` is active and releasing locks.

---

## 🔄 2. Cutover Execution (React Frontend Endpoint Switch)

To point the React frontend to Django V2:

In `src/context/AppContext.jsx`:
```javascript
// Change base API route from /api/v1/auth.php to /api/v2/auth/
const AUTH_API_BASE = '/api/v2/auth/';
```

Rebuild production bundle:
```bash
npm run build
```

Upload `runonconsole-build.zip` and extract to `public_html/`.

---

## ⏪ 3. Instant Zero-Downtime Rollback Procedure

If any unexpected anomaly occurs after cutover:

1. Revert `AUTH_API_BASE` back to `/api/v1/auth.php` in `src/context/AppContext.jsx`.
2. Re-run `npm run build`.
3. Upload `runonconsole-build.zip` and extract to `public_html/`.
4. Purge cPanel LiteSpeed cache.

> [!NOTE]
> Because `/api/v1/` PHP files and legacy database tables were never deleted or modified, reverting takes less than 60 seconds and causes zero data corruption.
