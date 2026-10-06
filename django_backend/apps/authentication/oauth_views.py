import json
import secrets
import requests
from django.shortcuts import redirect
from django.http import JsonResponse
from django.conf import settings
from django.contrib.auth import login, get_user_model
from django.views.decorators.http import require_http_methods
from .models import OAuthAccountLinkV2

UserV2 = get_user_model()

@require_http_methods(["GET"])
def google_start_view(request):
    """GET /api/v2/auth/google/start/"""
    client_id = getattr(settings, 'GOOGLE_CLIENT_ID', '')
    redirect_uri = getattr(settings, 'GOOGLE_REDIRECT_URI', '')

    if not client_id or not redirect_uri:
        return JsonResponse({"success": False, "error": "Google OAuth is not configured on the server."}, status=503)

    state = secrets.token_hex(16)
    request.session['oauth_state'] = state

    google_auth_url = (
        f"https://accounts.google.com/o/oauth2/v2/auth?"
        f"client_id={client_id}&"
        f"redirect_uri={redirect_uri}&"
        f"response_type=code&"
        f"scope=openid%20email%20profile&"
        f"state={state}"
    )

    return redirect(google_auth_url)

@require_http_methods(["GET"])
def google_callback_view(request):
    """GET /api/v2/auth/google/callback/"""
    code = request.GET.get('code', '')
    state = request.GET.get('state', '')
    session_state = request.session.get('oauth_state', '')

    if not code or not state or state != session_state:
        return redirect("https://runonconsole.com/auth/login/?error=oauth_state_mismatch")

    client_id = settings.GOOGLE_CLIENT_ID
    client_secret = settings.GOOGLE_CLIENT_SECRET
    redirect_uri = settings.GOOGLE_REDIRECT_URI

    # Token Exchange
    try:
        token_resp = requests.post("https://oauth2.googleapis.com/token", data={
            'code': code,
            'client_id': client_id,
            'client_secret': client_secret,
            'redirect_uri': redirect_uri,
            'grant_type': 'authorization_code'
        }, timeout=10)

        if token_resp.status_code != 200:
            return redirect("https://runonconsole.com/auth/login/?error=oauth_token_exchange_failed")

        token_data = token_resp.json()
        access_token = token_data.get('access_token')

        # Fetch Google Profile
        user_info_resp = requests.get("https://www.googleapis.com/oauth2/v3/userinfo", headers={
            'Authorization': f"Bearer {access_token}"
        }, timeout=10)

        if user_info_resp.status_code != 200:
            return redirect("https://runonconsole.com/auth/login/?error=oauth_userinfo_failed")

        google_user = user_info_resp.json()
        google_sub = google_user.get('sub')
        email = google_user.get('email', '').lower().strip()
        name = google_user.get('name', 'Gamer')

    except Exception:
        return redirect("https://runonconsole.com/auth/login/?error=oauth_server_error")

    # OAuth Account Resolution & Safe Account Linking
    oauth_link = OAuthAccountLinkV2.objects.filter(provider='google', provider_user_id=google_sub).first()

    if oauth_link:
        user = oauth_link.user
    else:
        existing_user = UserV2.objects.filter(email=email).first()
        if existing_user:
            # Safe linking check: Link and verify
            user = existing_user
            user.is_verified = True
            user.status = 'active'
            user.save(update_fields=['is_verified', 'status'])

            OAuthAccountLinkV2.objects.create(
                user=user,
                provider='google',
                provider_user_id=google_sub,
                provider_email=email
            )
        else:
            base_u = email.split('@')[0][:20]
            final_u = base_u
            counter = 1
            while UserV2.objects.filter(username__iexact=final_u).exists():
                final_u = f"{base_u}_{counter}"
                counter += 1

            user = UserV2.objects.create_user(
                email=email,
                name=name,
                username=final_u,
                status='active',
                is_verified=True
            )

            OAuthAccountLinkV2.objects.create(
                user=user,
                provider='google',
                provider_user_id=google_sub,
                provider_email=email
            )

    login(request, user)
    request.session.cycle_key()

    return redirect("https://runonconsole.com/profile/")
