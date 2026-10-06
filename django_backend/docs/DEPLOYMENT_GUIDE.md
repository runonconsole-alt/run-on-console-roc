# Run On Console (ROC) - Parallel Django Backend (`/api/v2/`) Deployment Guide

This guide provides step-by-step instructions for deploying the parallel **Django LTS Backend (`/api/v2/`)** on HosterPK cPanel without altering the live React website, legacy PHP backend, or SEO routes.

---

## 🏗️ 1. Directory Placement Architecture

```text
/home2/runoncon/
├── django_backend/               <-- Store Django Application OUTSIDE public_html
│   ├── manage.py
│   ├── passenger_wsgi.py
│   ├── requirements.txt
│   ├── roc_backend/
│   ├── apps/
│   └── templates/
├── config/
│   ├── env.php                   <-- Private PHP Credentials
│   └── django.env                <-- Private Django Environment Variables
└── public_html/                  <-- Live Web Root (Unchanged React + /api/v1/)
```

---

## ⚙️ 2. Environment Configuration (`/home2/runoncon/config/django.env`)

Create `/home2/runoncon/config/django.env` outside `public_html`:

```ini
# Django Core Security
DJANGO_SECRET_KEY=generate-a-strong-random-50-character-secret-key
DJANGO_DEBUG=False
ALLOWED_HOSTS=runonconsole.com,www.runonconsole.com,api.runonconsole.com
CSRF_TRUSTED_ORIGINS=https://runonconsole.com,https://www.runonconsole.com,https://api.runonconsole.com

# Database Connection (MySQL / MariaDB)
DB_HOST=localhost
DB_PORT=3306
DB_NAME=runoncon_db
DB_USER=runoncon_dbuser
DB_PASSWORD=your_real_mysql_password

# HosterPK Production SMTP Configuration
SMTP_HOST=mail.runonconsole.com
SMTP_PORT=587
SMTP_USE_TLS=True
SMTP_USERNAME=noreply@runonconsole.com
SMTP_PASSWORD=your_real_mailbox_password
MAIL_FROM_ADDRESS=noreply@runonconsole.com
MAIL_FROM_NAME=Run On Console
MAIL_REPLY_TO=support@runonconsole.com

# Google OAuth Credentials
GOOGLE_CLIENT_ID=your_google_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=https://runonconsole.com/api/v2/auth/google/callback/

# Cloudflare Turnstile Verification
CAPTCHA_SITE_KEY=your_turnstile_site_key
CAPTCHA_SECRET_KEY=your_turnstile_secret_key
```

---

## 📦 3. cPanel Python Application Setup (Passenger)

### Option A: Same-Domain Passenger Mount at `/api/v2/`

1. Log in to cPanel -> **Setup Python App**.
2. Click **Create Application**:
   - **Python Version**: `3.10` / `3.11` / `3.12` (Select latest available).
   - **Application Root**: `django_backend`
   - **Application URL**: `api/v2`
   - **Application Startup File**: `passenger_wsgi.py`
   - **Application Entry Point**: `application`
3. Click **Create**.
4. In cPanel Terminal, enter the virtualenv and install pinned dependencies:
   ```bash
   source /home2/runoncon/virtualenv/django_backend/3.10/bin/activate
   cd /home2/runoncon/django_backend
   pip install -r requirements.txt
   ```

### Option B: Subdomain Mount at `api.runonconsole.com` (If Path Mounting Unsupported)

1. cPanel -> **Subdomains** -> Create `api.runonconsole.com`.
2. cPanel -> **Setup Python App**:
   - **Application Root**: `django_backend`
   - **Application URL**: `api.runonconsole.com`
   - **Application Startup File**: `passenger_wsgi.py`

---

## 🗄️ 4. Database Migrations & Static File Collection

Execute via cPanel Terminal inside the activated virtualenv:

```bash
cd /home2/runoncon/django_backend

# 1. Run isolated V2 database migrations (Creates v2_users, v2_email_queue, etc.)
python manage.py makemigrations
python manage.py migrate

# 2. Collect static files
python manage.py collectstatic --noinput
```

---

## ⏰ 5. cPanel Cron Setup for Email Queue Worker

In cPanel -> **Cron Jobs**:

- **Schedule**: Once Per Minute (`* * * * *`)
- **Command**:
  ```bash
  /home2/runoncon/virtualenv/django_backend/3.10/bin/python /home2/runoncon/django_backend/manage.py process_email_queue >> /home2/runoncon/v2-email-worker.log 2>&1
  ```

---

## 🔑 6. Google OAuth Console Configuration

In [Google Cloud Console](https://console.cloud.google.com/):

1. **Authorized JavaScript Origins**:
   - `https://runonconsole.com`
   - `https://www.runonconsole.com`
2. **Authorized Redirect URIs**:
   - `https://runonconsole.com/api/v2/auth/google/callback/`
   - `https://api.runonconsole.com/api/v2/auth/google/callback/`

---

## 🩺 7. Health Check Verification

Test in browser or via cURL:

```bash
curl -i https://runonconsole.com/api/v2/health/
```

Expected Response (`HTTP 200 OK`):
```json
{
  "status": "healthy",
  "version": "v2.0.0-django",
  "database": "connected",
  "engine": "Django LTS (MariaDB/MySQL)"
}
```
