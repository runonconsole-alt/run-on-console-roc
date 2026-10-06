import hashlib
import secrets
import requests
from datetime import timedelta
from django.utils import timezone
from django.conf import settings
from .models import RateLimitRecordV2

def hash_token(token):
    return hashlib.sha256(token.encode('utf-8')).hexdigest()

def hash_email(email):
    return hashlib.sha256(email.lower().strip().encode('utf-8')).hexdigest()

def generate_secure_token():
    return secrets.token_hex(32)

def verify_turnstile(captcha_token, remote_ip=None):
    """
    Server-side Cloudflare Turnstile Verification (Fail-Closed)
    """
    secret_key = getattr(settings, 'CAPTCHA_SECRET_KEY', '')
    site_key = getattr(settings, 'CAPTCHA_SITE_KEY', '')

    if not secret_key or not site_key:
        return False, "Security verification is not configured on the server."

    if not captcha_token:
        return False, "CAPTCHA verification token is missing. Please complete the security check."

    verify_url = "https://challenges.cloudflare.com/turnstile/v0/siteverify"
    payload = {
        'secret': secret_key,
        'response': captcha_token,
    }
    if remote_ip:
        payload['remoteip'] = remote_ip

    try:
        resp = requests.post(verify_url, data=payload, timeout=5)
        if resp.status_code == 200:
            data = resp.json()
            if data.get('success') is True:
                return True, "Success"
            return False, "CAPTCHA verification failed. Please try again."
    except Exception as e:
        return False, "Network error during CAPTCHA verification."

    return False, "CAPTCHA verification failed."

def is_rate_limited(ip_address, email, action='login', max_attempts=5, window_seconds=900):
    email_h = hash_email(email) if email else ''
    cutoff = timezone.now() - timedelta(seconds=window_seconds)

    attempts = RateLimitRecordV2.objects.filter(
        action=action,
        attempted_at__gte=cutoff
    ).filter(
        models.Q(ip_address=ip_address) | models.Q(email_hash=email_h)
    ).count()

    return attempts >= max_attempts

def log_rate_attempt(ip_address, email, action='login'):
    try:
        email_h = hash_email(email) if email else ''
        RateLimitRecordV2.objects.create(
            ip_address=ip_address,
            email_hash=email_h,
            action=action
        )
    except Exception:
        pass
