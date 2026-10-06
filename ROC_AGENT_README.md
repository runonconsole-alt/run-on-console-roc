# Run On Console — ROC Agent Phase 1 Intelligence Architecture & CLI Administration

ROC Agent is an AI-powered gaming, product discovery, and hardware compatibility assistant connected to real Run On Console database records.

---

## 🛠️ Security & Production Guardrails

1. **No Automatic Web Migrations**:
   - `api/v1/agent/migrations.php` has been completely deleted.
   - All migrations must be executed strictly via CLI (`php api/v1/cron/cli-migrate-roc-agent.php`).
   - Pre-deployment cleanup command for live server:
     ```bash
     rm -f /home2/runoncon/public_html/api/v1/agent/migrations.php
     ```

2. **Atomic IP-Scoped Rate Limiting (`config-agent.php`)**:
   - Protects all read & write endpoints using `rocAgentCheckAndLogRateLimit($action, $maxAttempts, $windowSeconds)`.
   - Atomically logs request attempts FIRST before checking window counts.
   - Does NOT share dummy email identities across visitors.

3. **Separated Blog Publication Backfill (`backfill-blog-status.php`)**:
   - Schema migration (`cli-migrate-roc-agent.php`) adds `status ENUM('published', 'draft') NOT NULL DEFAULT 'draft'` without automatically updating records to published.
   - Separate CLI command displays proposed public mapping for owner review and requires `--confirm` flag to execute:
     ```bash
     # Preview mapping without modifying DB:
     php api/v1/cron/backfill-blog-status.php

     # Apply approved mapping:
     php api/v1/cron/backfill-blog-status.php --confirm
     ```

4. **Strict OS & Hardware Compatibility Engine (`check-compatibility.php`)**:
   - Evaluates OS Family, Architecture (64-bit vs 32-bit), and Version Numbers independently.
   - Returns `"Unable to Determine"` when user architecture or OS version is missing.
   - Windows 7 fails Windows 11 requirements (`7.0 < 11.0`).

5. **Conversation Session Isolation & Security (`conversations.php`)**:
   - `GET` endpoints are read-only and do not insert conversation records.
   - `POST` endpoints validate method, CSRF tokens (`X-CSRF-Token`), and rate limits before inserting records.
   - Guests access ONLY conversations tied to their secure `guest_session_id`. Logged-in users access ONLY conversations tied to their `user_id`.

---

## 📋 CLI Commands

| Command | Purpose | Parameters / Options |
| :--- | :--- | :--- |
| `php api/v1/cron/cli-migrate-roc-agent.php` | Safe CLI table & index creation | CLI execution required |
| `php api/v1/cron/backfill-blog-status.php` | Standalone blog publication status backfill | Run plain for preview<br/>Use `--confirm` to apply |
| `php api/v1/cron/seed-roc-agent-games.php` | Optional game & hardware mapping seeding | Standalone seed script |
