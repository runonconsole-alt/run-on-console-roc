import os
from pathlib import Path
from dotenv import load_dotenv

BASE_DIR = Path(__file__).resolve().parent.parent

# Load private server environment variables
env_paths = [
    Path('/home2/runoncon/config/django.env'),
    Path('/home/runoncon/config/django.env'),
    BASE_DIR / '.env'
]

for env_path in env_paths:
    if env_path.exists():
        load_dotenv(env_path)
        break

# Core Production Security
SECRET_KEY = os.getenv('DJANGO_SECRET_KEY', '')
DEBUG = os.getenv('DJANGO_DEBUG', 'False').lower() in ('true', '1', 't')

allowed_hosts_raw = os.getenv('ALLOWED_HOSTS', 'runonconsole.com,www.runonconsole.com,api.runonconsole.com,127.0.0.1,localhost')
ALLOWED_HOSTS = [host.strip() for host in allowed_hosts_raw.split(',') if host.strip()]

# Custom User Model Isolation
AUTH_USER_MODEL = 'authentication.UserV2'

# Installed Applications
INSTALLED_APPS = [
    'django.contrib.admin',
    'django.contrib.auth',
    'django.contrib.contenttypes',
    'django.contrib.sessions',
    'django.contrib.messages',
    'django.contrib.staticfiles',
    
    # Third-Party Libraries
    'corsheaders',
    
    # Local Parallel V2 Application Modules
    'apps.authentication',
    'apps.user_profiles',
    'apps.email_queue',
]

MIDDLEWARE = [
    'corsheaders.middleware.CorsMiddleware',
    'django.middleware.security.SecurityMiddleware',
    'django.contrib.sessions.middleware.SessionMiddleware',
    'django.middleware.common.CommonMiddleware',
    'django.middleware.csrf.CsrfViewMiddleware',
    'django.contrib.auth.middleware.AuthenticationMiddleware',
    'django.contrib.messages.middleware.MessageMiddleware',
    'django.middleware.clickjacking.XFrameOptionsMiddleware',
]

ROOT_URLCONF = 'roc_backend.urls'

TEMPLATES = [
    {
        'BACKEND': 'django.template.backends.django.DjangoTemplates',
        'DIRS': [BASE_DIR / 'templates'],
        'APP_DIRS': True,
        'OPTIONS': {
            'context_processors': [
                'django.template.context_processors.debug',
                'django.template.context_processors.request',
                'django.contrib.auth.context_processors.auth',
                'django.contrib.messages.context_processors.messages',
            ],
        },
    },
]

WSGI_APPLICATION = 'roc_backend.wsgi.application'

# Database Configuration (MySQL / MariaDB or SQLite fallback for development)
DB_NAME = os.getenv('DB_NAME', '')
DB_USER = os.getenv('DB_USER', '')
DB_PASS = os.getenv('DB_PASSWORD', '')
DB_HOST = os.getenv('DB_HOST', 'localhost')
DB_PORT = os.getenv('DB_PORT', '3306')

if DB_NAME and DB_USER:
    DATABASES = {
        'default': {
            'ENGINE': 'django.db.backends.mysql',
            'NAME': DB_NAME,
            'USER': DB_USER,
            'PASSWORD': DB_PASS,
            'HOST': DB_HOST,
            'PORT': DB_PORT,
            'OPTIONS': {
                'charset': 'utf8mb4',
                'init_command': "SET sql_mode='STRICT_TRANS_TABLES'",
            },
        }
    }
else:
    DATABASES = {
        'default': {
            'ENGINE': 'django.db.backends.sqlite3',
            'NAME': BASE_DIR / 'db.sqlite3',
        }
    }

# Password Validation
AUTH_PASSWORD_VALIDATORS = [
    {'NAME': 'django.contrib.auth.password_validation.UserAttributeSimilarityValidator'},
    {'NAME': 'django.contrib.auth.password_validation.MinimumLengthValidator', 'OPTIONS': {'min_length': 8}},
    {'NAME': 'django.contrib.auth.password_validation.CommonPasswordValidator'},
    {'NAME': 'django.contrib.auth.password_validation.NumericPasswordValidator'},
]

# Password Hashes (Argon2id with PBKDF2 Fallback)
PASSWORD_HASHERS = [
    'django.contrib.auth.hashers.Argon2PasswordHasher',
    'django.contrib.auth.hashers.PBKDF2PasswordHasher',
    'django.contrib.auth.hashers.PBKDF2SHA1PasswordHasher',
    'django.contrib.auth.hashers.BCryptSHA256PasswordHasher',
]

# Internationalization
LANGUAGE_CODE = 'en-us'
TIME_ZONE = 'UTC'
USE_I18N = True
USE_TZ = True

# Static Files
STATIC_URL = '/static/'
STATIC_ROOT = BASE_DIR / 'staticfiles'

MEDIA_URL = '/media/'
MEDIA_ROOT = BASE_DIR / 'media'

DEFAULT_AUTO_FIELD = 'django.db.models.BigAutoField'

# Session & Security Cookie Policy
SESSION_ENGINE = 'django.contrib.sessions.backends.db'
SESSION_COOKIE_NAME = 'roc_v2_session'
SESSION_COOKIE_AGE = 86400 * 30  # 30 Days
SESSION_COOKIE_HTTPONLY = True
SESSION_COOKIE_SECURE = not DEBUG
SESSION_COOKIE_SAMESITE = 'Lax'

CSRF_COOKIE_NAME = 'roc_v2_csrftoken'
CSRF_COOKIE_HTTPONLY = False  # Required for frontend X-CSRFToken header extraction
CSRF_COOKIE_SECURE = not DEBUG
CSRF_COOKIE_SAMESITE = 'Lax'

csrf_origins_raw = os.getenv('CSRF_TRUSTED_ORIGINS', 'https://runonconsole.com,https://www.runonconsole.com,https://api.runonconsole.com')
CSRF_TRUSTED_ORIGINS = [origin.strip() for origin in csrf_origins_raw.split(',') if origin.strip()]

# CORS Configuration
CORS_ALLOW_CREDENTIALS = True
cors_origins_raw = os.getenv('CORS_ALLOWED_ORIGINS', 'https://runonconsole.com,https://www.runonconsole.com,https://api.runonconsole.com')
CORS_ALLOWED_ORIGINS = [origin.strip() for origin in cors_origins_raw.split(',') if origin.strip()]

SECURE_BROWSER_XSS_FILTER = True
SECURE_CONTENT_TYPE_NOSNIFF = True
X_FRAME_OPTIONS = 'SAMEORIGIN'

# Production HosterPK SMTP Email Backend Settings
EMAIL_BACKEND = 'django.core.mail.backends.smtp.EmailBackend'
EMAIL_HOST = os.getenv('SMTP_HOST', 'mail.runonconsole.com')
EMAIL_PORT = int(os.getenv('SMTP_PORT', 587))
EMAIL_USE_TLS = os.getenv('SMTP_USE_TLS', 'True').lower() in ('true', '1', 't')
EMAIL_HOST_USER = os.getenv('SMTP_USERNAME', 'noreply@runonconsole.com')
EMAIL_HOST_PASSWORD = os.getenv('SMTP_PASSWORD', '')
DEFAULT_FROM_EMAIL = f"{os.getenv('MAIL_FROM_NAME', 'Run On Console')} <{os.getenv('MAIL_FROM_ADDRESS', 'noreply@runonconsole.com')}>"
SERVER_EMAIL = DEFAULT_FROM_EMAIL

# Google OAuth & Cloudflare Turnstile Configuration
GOOGLE_CLIENT_ID = os.getenv('GOOGLE_CLIENT_ID', '')
GOOGLE_CLIENT_SECRET = os.getenv('GOOGLE_CLIENT_SECRET', '')
GOOGLE_REDIRECT_URI = os.getenv('GOOGLE_REDIRECT_URI', 'https://runonconsole.com/api/v2/auth/google/callback/')

CAPTCHA_SITE_KEY = os.getenv('CAPTCHA_SITE_KEY', '')
CAPTCHA_SECRET_KEY = os.getenv('CAPTCHA_SECRET_KEY', '')
