========================================================================
RUN ON CONSOLE (ROC) — PRODUCTION DEPLOYMENT, SCHEMA MIGRATION & ROLLBACK GUIDE
Target Server Extraction Directory: /home2/runoncon/public_html/
========================================================================

⚠️ STRICT DEPLOYMENT GUARDRAILS:
- Do NOT extract or execute any scripts on live production without explicit owner window approval.
- Execute all schema migrations and backfill scripts on an isolated staging database first.
- Optional game seeding script (`seed-roc-agent-games.php`) MUST remain disabled until game requirements and hardware comparison data are reviewed.

------------------------------------------------------------------------
SECTION 1: LEGACY PUBLIC MIGRATION CLEANUP (CRITICAL SECURITY STEP)
------------------------------------------------------------------------
Removing a file from a deployment ZIP does NOT automatically delete an existing legacy file from the live web server.
Before extracting updated backend ZIP packages, run the following command in cPanel Terminal or SSH:

    rm -f /home2/runoncon/public_html/api/v1/agent/migrations.php

This removes the legacy unauthenticated HTTP migration endpoint to prevent public execution.

------------------------------------------------------------------------
SECTION 2: STEP-BY-STEP SCHEMA MIGRATION EXECUTION ORDER
------------------------------------------------------------------------

1. FRONTEND BUILD DEPLOYMENT (runonconsole-build.zip):
   - Upload `runonconsole-build.zip` to `/home2/runoncon/public_html/`.
   - Extract files directly into `/home2/runoncon/public_html/`.
   - Verify `index.html` contains single active bundle asset scripts and pre-rendered HTML routes.

2. BACKEND API REPAIR DEPLOYMENT (run-on-console-php-v1-repair.zip):
   - Upload `run-on-console-php-v1-repair.zip` to `/home2/runoncon/public_html/`.
   - Extract files into `/home2/runoncon/public_html/`.

3. PRIVATE CLI SCHEMA MIGRATION:
   - Run the self-contained CLI-only migration script via cPanel Terminal or SSH:
     
     cd /home2/runoncon/public_html
     php api/v1/cron/cli-migrate-roc-agent.php

   - Migration Actions Performed:
     a) Adds `status ENUM('published', 'draft') DEFAULT 'draft'` to `blogs` table if missing (defaults unclassified records to 'draft').
     b) Independently inspects and adds missing `created_at` and `updated_at` timestamps to `game_requirements`.
     c) Verifies deep unique index `idx_game_req_type` (`NON_UNIQUE = 0`, `game_id`, `requirement_type`). Detects duplicate rows and halts safely for review if found.

------------------------------------------------------------------------
SECTION 3: BLOG PUBLICATION BACKFILL & OWNER APPROVAL PROCEDURE
------------------------------------------------------------------------

The blog publication backfill script does NOT run automatically during schema migration. It requires explicit owner review of actual database records before publishing.

1. PREVIEW DATABASE MATCHES & CONFLICT AUDIT:
   - Execute preview mode CLI command:

     php api/v1/cron/backfill-blog-status.php

   - This command queries the actual database by ID and Slug independently, displaying matched record ID, slug, title, and current status.
   - Any missing records or ID vs Slug conflicts will be flagged and execution halted.

2. APPLY EXPLICIT APPROVED PUBLICATION STATUS:
   - Once the owner reviews and approves the displayed matched records, execute with `--confirm`:

     php api/v1/cron/backfill-blog-status.php --confirm

   - Only explicitly matched database records will be updated to `status = 'published'`. Unmapped entries remain `draft`.

------------------------------------------------------------------------
SECTION 4: EMERGENCY ROLLBACK PREPARATION
------------------------------------------------------------------------

Before initiating any database migration on staging or production:

1. CREATE FULL DATABASE DUMP:
   
   mysqldump -u <db_user> -p <db_name> > /home2/runoncon/backups/pre_migration_backup_$(date +%Y%m%d_%H%M%S).sql

2. ROLLBACK EXECUTION PROCEDURE:
   If a migration failure occurs or unexpected DB issues arise:
   
   mysql -u <db_user> -p <db_name> < /home2/runoncon/backups/pre_migration_backup_YYYYMMDD_HHMMSS.sql

========================================================================
