import json
from django.http import JsonResponse
from django.views.decorators.csrf import csrf_protect
from django.views.decorators.http import require_http_methods
from django.contrib.auth.hashers import check_password
from .models import UserProfileV2, SavedProductV2, PriceAlertV2, UserPreferenceV2, CommentHistoryV2

def require_verified_user(view_func):
    """Decorator requiring authenticated & verified user status"""
    def _wrapped(request, *args, **kwargs):
        if not request.user.is_authenticated:
            return JsonResponse({"success": False, "error": "Authentication required. Please sign in."}, status=401)
        if not request.user.is_verified:
            return JsonResponse({"success": False, "error": "VERIFICATION_REQUIRED", "message": "Please verify your email address to use this feature."}, status=403)
        return view_func(request, *args, **kwargs)
    return _wrapped

@csrf_protect
@require_http_methods(["GET", "PUT"])
def profile_detail_view(request):
    """GET/PUT /api/v2/profile/"""
    if not request.user.is_authenticated:
        return JsonResponse({"success": False, "error": "Authentication required."}, status=401)

    user = request.user
    profile, _ = UserProfileV2.objects.get_or_create(user=user)

    if request.method == "GET":
        return JsonResponse({
            "success": True,
            "profile": {
                "id": user.id,
                "uuid": str(user.uuid),
                "name": user.name,
                "email": user.email,
                "username": user.username,
                "is_verified": user.is_verified,
                "country": profile.country,
                "currency": profile.currency,
                "avatar_icon": profile.avatar_icon,
                "avatar_bg": profile.avatar_bg,
                "bio": profile.bio,
                "marketing_opt_in": profile.marketing_opt_in
            }
        })

    # PUT update profile
    try:
        data = json.loads(request.body)
    except Exception:
        data = request.POST

    name = data.get('name', '').strip()
    country = data.get('country', '').strip()
    currency = data.get('currency', '').strip()
    avatar_icon = data.get('avatar_icon', '').strip()
    avatar_bg = data.get('avatar_bg', '').strip()
    bio = data.get('bio', '').strip()

    if name:
        user.name = name
        user.save(update_fields=['name'])

    if country: profile.country = country
    if currency: profile.currency = currency
    if avatar_icon: profile.avatar_icon = avatar_icon
    if avatar_bg: profile.avatar_bg = avatar_bg
    if bio is not None: profile.bio = bio

    profile.save()

    return JsonResponse({
        "success": True,
        "message": "Profile updated successfully!",
        "profile": {
            "name": user.name,
            "country": profile.country,
            "currency": profile.currency,
            "avatar_icon": profile.avatar_icon,
            "avatar_bg": profile.avatar_bg,
            "bio": profile.bio
        }
    })

@csrf_protect
@require_http_methods(["GET", "POST", "DELETE"])
def saved_products_view(request):
    """GET/POST/DELETE /api/v2/profile/saved-products/"""
    if not request.user.is_authenticated:
        return JsonResponse({"success": False, "error": "Authentication required."}, status=401)

    user = request.user

    if request.method == "GET":
        saved = list(SavedProductV2.objects.filter(user=user).values('id', 'product_slug', 'created_at'))
        return JsonResponse({"success": True, "saved_products": saved})

    try:
        data = json.loads(request.body)
    except Exception:
        data = request.POST

    slug = data.get('product_slug', '').strip()
    if not slug:
        return JsonResponse({"success": False, "error": "Product slug is required."}, status=400)

    if request.method == "POST":
        item, created = SavedProductV2.objects.get_or_create(user=user, product_slug=slug)
        return JsonResponse({"success": True, "message": "Product saved!", "created": created})

    if request.method == "DELETE":
        SavedProductV2.objects.filter(user=user, product_slug=slug).delete()
        return JsonResponse({"success": True, "message": "Product removed from saved items."})

@csrf_protect
@require_http_methods(["GET", "POST", "DELETE"])
@require_verified_user
def price_alerts_view(request):
    """GET/POST/DELETE /api/v2/profile/price-alerts/ (Requires Verified Email)"""
    user = request.user

    if request.method == "GET":
        alerts = list(PriceAlertV2.objects.filter(user=user).values('id', 'product_slug', 'target_price', 'is_active', 'created_at'))
        return JsonResponse({"success": True, "price_alerts": alerts})

    try:
        data = json.loads(request.body)
    except Exception:
        data = request.POST

    slug = data.get('product_slug', '').strip()
    if not slug:
        return JsonResponse({"success": False, "error": "Product slug is required."}, status=400)

    if request.method == "POST":
        target_price = data.get('target_price')
        if not target_price:
            return JsonResponse({"success": False, "error": "Target price is required."}, status=400)

        alert, created = PriceAlertV2.objects.update_or_create(
            user=user,
            product_slug=slug,
            defaults={'target_price': target_price, 'is_active': True}
        )
        return JsonResponse({"success": True, "message": "Price alert created!", "created": created})

    if request.method == "DELETE":
        PriceAlertV2.objects.filter(user=user, product_slug=slug).delete()
        return JsonResponse({"success": True, "message": "Price alert deleted."})

@csrf_protect
@require_http_methods(["GET"])
def data_export_view(request):
    """GET /api/v2/profile/export-data/"""
    if not request.user.is_authenticated:
        return JsonResponse({"success": False, "error": "Authentication required."}, status=401)

    user = request.user
    profile = getattr(user, 'profile_v2', None)
    saved = list(SavedProductV2.objects.filter(user=user).values('product_slug', 'created_at'))
    alerts = list(PriceAlertV2.objects.filter(user=user).values('product_slug', 'target_price', 'created_at'))
    comments = list(CommentHistoryV2.objects.filter(user=user).values('page_slug', 'comment_text', 'created_at'))

    export_payload = {
        "user": {
            "uuid": str(user.uuid),
            "name": user.name,
            "email": user.email,
            "username": user.username,
            "created_at": user.created_at.isoformat()
        },
        "profile": {
            "country": profile.country if profile else '',
            "currency": profile.currency if profile else '',
            "bio": profile.bio if profile else ''
        },
        "saved_products": saved,
        "price_alerts": alerts,
        "comment_history": comments
    }

    return JsonResponse({"success": True, "export_data": export_payload})

@csrf_protect
@require_http_methods(["POST"])
def delete_account_view(request):
    """POST /api/v2/profile/delete-account/"""
    if not request.user.is_authenticated:
        return JsonResponse({"success": False, "error": "Authentication required."}, status=401)

    user = request.user
    try:
        data = json.loads(request.body)
    except Exception:
        data = request.POST

    password = data.get('password', '')
    if user.has_usable_password() and not check_password(password, user.password):
        return JsonResponse({"success": False, "error": "Incorrect password confirmation."}, status=400)

    user.delete()
    return JsonResponse({"success": True, "message": "Your account has been deleted permanently."})
