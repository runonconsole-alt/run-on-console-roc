import json
import random
from datetime import timedelta
from django.utils import timezone
from django.contrib.auth import authenticate, login, logout, get_user_model
from django.contrib.auth.hashers import make_password, check_password
from django.http import JsonResponse
from django.views.decorators.csrf import ensure_csrf_cookie, csrf_protect
from django.views.decorators.http import require_http_methods
from django.middleware.csrf import get_token
from django.conf import settings
from django.db import transaction

from .models import (
    UserV2, EmailVerificationChallengeV2, PasswordResetTokenV2,
    RateLimitRecordV2, OAuthAccountLinkV2
)
from .services import (
    verify_turnstile, is_rate_limited, log_rate_attempt,
    generate_secure_token, hash_token
)
from .serializers import validate_username_format
from apps.email_queue.services import queue_email

def get_client_ip(request):
    x_forwarded_for = request.META.get('HTTP_X_FORWARDED_FOR')
    if x_forwarded_for:
        return x_forwarded_for.split(',')[0].strip()
    return request.META.get('REMOTE_ADDR', '')

def get_auth_config():
    return {
        'smtp_configured': bool(settings.EMAIL_HOST and settings.EMAIL_HOST_USER and settings.EMAIL_HOST_PASSWORD),
        'google_oauth_configured': bool(settings.GOOGLE_CLIENT_ID and settings.GOOGLE_CLIENT_SECRET),
        'captcha_configured': bool(settings.CAPTCHA_SITE_KEY and settings.CAPTCHA_SECRET_KEY),
        'google_client_id': settings.GOOGLE_CLIENT_ID,
        'captcha_site_key': settings.CAPTCHA_SITE_KEY,
        'captcha_provider': 'turnstile'
    }

@ensure_csrf_cookie
@require_http_methods(["GET"])
def session_check_view(request):
    """GET /api/v2/auth/session/"""
    csrf_token = get_token(request)
    auth_config = get_auth_config()

    if not request.user.is_authenticated or not request.user.is_active:
        return JsonResponse({
            "success": True,
            "authenticated": False,
            "csrf_token": csrf_token,
            "user": None,
            "config": auth_config
        })

    user = request.user
    profile_data = getattr(user, 'profile_v2', None)

    return JsonResponse({
        "success": True,
        "authenticated": True,
        "csrf_token": csrf_token,
        "user": {
            "id": user.id,
            "uuid": str(user.uuid),
            "name": user.name,
            "email": user.email,
            "username": user.username,
            "role": user.role,
            "status": user.status,
            "is_verified": user.is_verified,
            "country": profile_data.country if profile_data else 'United States',
            "currency": profile_data.currency if profile_data else 'USD',
            "avatar_icon": profile_data.avatar_icon if profile_data else 'gamepad',
            "avatar_bg": profile_data.avatar_bg if profile_data else 'from-emerald-600 to-teal-500',
            "bio": profile_data.bio if profile_data else ''
        },
        "config": auth_config
    })

@csrf_protect
@require_http_methods(["POST"])
def signup_view(request):
    """POST /api/v2/auth/signup/"""
    try:
        data = json.loads(request.body)
    except Exception:
        data = request.POST

    name = data.get('name', '').strip()
    username_input = data.get('username', '').strip()
    email = data.get('email', '').strip().lower()
    password = data.get('password', '')
    confirm_password = data.get('confirmPassword', '')
    agree_terms = bool(data.get('agreeTerms'))
    captcha_token = data.get('captchaToken', '')
    client_ip = get_client_ip(request)

    if not name or not email or not password:
        return JsonResponse({"success": False, "error": "Name, email, and password are required."}, status=400)

    if len(password) < 8:
        return JsonResponse({"success": False, "error": "Password must be at least 8 characters long."}, status=422)

    if password != confirm_password:
        return JsonResponse({"success": False, "error": "Passwords do not match."}, status=422)

    if not agree_terms:
        return JsonResponse({"success": False, "error": "You must agree to the Terms of Service and Privacy Policy."}, status=422)

    # Server-Side Cloudflare Turnstile Verification
    captcha_ok, captcha_msg = verify_turnstile(captcha_token, client_ip)
    if not captcha_ok:
        return JsonResponse({"success": False, "error": captcha_msg}, status=422)

    # Rate Limit Check
    log_rate_attempt(client_ip, email, 'signup')
    if is_rate_limited(client_ip, email, 'signup', 5, 900):
        return JsonResponse({"success": False, "error": "Too many registration attempts. Please try again in 15 minutes."}, status=429)

    # Username Validation
    if username_input:
        valid_u, msg_u = validate_username_format(username_input)
        if not valid_u:
            return JsonResponse({"success": False, "error": msg_u}, status=422)
        final_username = username_input
    else:
        base_u = re.sub(r'[^a-zA-Z0-9_]', '', email.split('@')[0])[:20]
        if len(base_u) < 3: base_u = 'gamer_' + str(random.randint(100, 999))
        final_username = base_u
        counter = 1
        while UserV2.objects.filter(username__iexact=final_username).exists():
            final_username = f"{base_u}_{counter}"
            counter += 1

    # Check Existing Email (Generic Response to Prevent Account Enumeration)
    if UserV2.objects.filter(email=email).exists():
        return JsonResponse({"success": False, "error": "An account with this email address already exists. Please Sign In."}, status=422)

    try:
        with transaction.atomic():
            user = UserV2.objects.create_user(
                email=email,
                name=name,
                username=final_username,
                password=password,
                status='pending_verification',
                is_verified=False
            )

            six_digit_code = str(random.randint(100000, 999999))
            code_hash = make_password(six_digit_code)
            raw_token = generate_secure_token()
            link_token_hash = hash_token(raw_token)
            expires_at = timezone.now() + timedelta(minutes=10)

            EmailVerificationChallengeV2.objects.create(
                user=user,
                code_hash=code_hash,
                link_token_hash=link_token_hash,
                expires_at=expires_at
            )

            verification_url = f"https://runonconsole.com/auth/verify-email/?token={raw_token}"
            email_subject = "Verify your Run On Console account 🎮"
            email_body = f"""
            <!DOCTYPE html>
            <html>
            <body style='font-family: Arial, sans-serif; background-color: #0F172A; color: #F8FAFC; padding: 20px;'>
              <div style='max-width: 580px; margin: 0 auto; background: #1E293B; border: 2px solid #10B981; border-radius: 16px; padding: 30px; text-align: center;'>
                <h2 style='color: #10B981; margin-bottom: 5px;'>RUN ON CONSOLE</h2>
                <h3 style='color: #FFFFFF;'>Welcome, {name}!</h3>
                <p style='color: #94A3B8;'>Enter this 6-digit verification code on the verification page:</p>
                <div style='margin: 25px 0; background: #0F172A; border: 2px dashed #10B981; padding: 20px; border-radius: 12px; display: inline-block;'>
                  <span style='font-family: monospace; font-size: 36px; font-weight: 900; letter-spacing: 8px; color: #34D399;'>{six_digit_code}</span>
                </div>
                <p style='color: #F59E0B; font-size: 12px; font-weight: bold;'>This code expires in 10 minutes.</p>
                <div style='margin: 30px 0;'>
                  <a href='{verification_url}' style='background: #10B981; color: #FFFFFF; font-weight: bold; text-decoration: none; padding: 14px 28px; border-radius: 10px; display: inline-block;'>Verify My Account →</a>
                </div>
              </div>
            </body>
            </html>"""

            queue_id = queue_email(email, email_subject, email_body)
            if not queue_id:
                raise Exception("Failed to queue activation email.")

        return JsonResponse({
            "success": True,
            "message": "Account created! Please enter the 6-digit code or click the verification link sent to your email."
        }, status=201)

    except Exception as e:
        return JsonResponse({"success": False, "error": f"Registration failed: {str(e)}"}, status=500)

@csrf_protect
@require_http_methods(["POST"])
def login_view(request):
    """POST /api/v2/auth/login/"""
    try:
        data = json.loads(request.body)
    except Exception:
        data = request.POST

    email = data.get('email', '').strip().lower()
    password = data.get('password', '')
    client_ip = get_client_ip(request)

    if not email or not password:
        return JsonResponse({"success": False, "error": "Please enter both email and password."}, status=400)

    log_rate_attempt(client_ip, email, 'login')
    if is_rate_limited(client_ip, email, 'login', 5, 900):
        return JsonResponse({"success": False, "error": "Too many failed login attempts. Please try again in 15 minutes."}, status=429)

    user = authenticate(request, username=email, password=password)
    if user is None:
        return JsonResponse({"success": False, "error": "Invalid email or password."}, status=401)

    if not user.is_verified:
        return JsonResponse({
            "success": False,
            "error": "VERIFICATION_REQUIRED",
            "message": "Please verify your email address before logging in.",
            "email": email
        }, status=403)

    login(request, user)
    request.session.cycle_key()  # Session Rotation after login

    return JsonResponse({
        "success": True,
        "message": f"Welcome back, {user.name}!",
        "user": {
            "id": user.id,
            "uuid": str(user.uuid),
            "name": user.name,
            "email": user.email,
            "role": user.role
        }
    })

@csrf_protect
@require_http_methods(["POST"])
def logout_view(request):
    """POST /api/v2/auth/logout/"""
    logout(request)
    return JsonResponse({"success": True, "message": "Logged out successfully."})

@csrf_protect
@require_http_methods(["POST"])
def verify_email_view(request):
    """POST /api/v2/auth/verify-email/"""
    try:
        data = json.loads(request.body)
    except Exception:
        data = request.POST

    code = data.get('code', '').strip()
    raw_token = data.get('token', '').strip()
    email = data.get('email', '').strip().lower()

    if not code and not raw_token:
        return JsonResponse({"success": False, "error": "Please provide a verification code or token."}, status=400)

    if raw_token:
        token_h = hash_token(raw_token)
        challenge = EmailVerificationChallengeV2.objects.filter(
            link_token_hash=token_h,
            consumed_at__isnull=True,
            expires_at__gt=timezone.now()
        ).first()

        if challenge:
            user = challenge.user
            user.is_verified = True
            user.status = 'active'
            user.save(update_fields=['is_verified', 'status'])
            challenge.consumed_at = timezone.now()
            challenge.save(update_fields=['consumed_at'])
            return JsonResponse({"success": True, "message": "Email verified successfully! Your account is now active."})

    if code and email:
        user = UserV2.objects.filter(email=email).first()
        if user:
            challenges = EmailVerificationChallengeV2.objects.filter(
                user=user,
                consumed_at__isnull=True,
                expires_at__gt=timezone.now()
            ).order_by('-created_at')

            for ch in challenges:
                if check_password(code, ch.code_hash):
                    user.is_verified = True
                    user.status = 'active'
                    user.save(update_fields=['is_verified', 'status'])
                    ch.consumed_at = timezone.now()
                    ch.save(update_fields=['consumed_at'])
                    return JsonResponse({"success": True, "message": "Email verified successfully! Your account is now active."})

    return JsonResponse({"success": False, "error": "Invalid or expired verification code."}, status=400)
