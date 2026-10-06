from django.contrib import admin
from django.urls import path, include
from django.http import JsonResponse
from django.db import connection

def health_check(request):
    """Production Health Check Endpoint (/api/v2/health/)"""
    db_ok = False
    try:
        with connection.cursor() as cursor:
            cursor.execute("SELECT 1")
            db_ok = True
    except Exception:
        db_ok = False

    status_code = 200 if db_ok else 503
    return JsonResponse({
        "status": "healthy" if db_ok else "unhealthy",
        "version": "v2.0.0-django",
        "database": "connected" if db_ok else "disconnected",
        "engine": "Django LTS (MariaDB/MySQL)"
    }, status=status_code)

urlpatterns = [
    # Health Check Endpoint
    path('api/v2/health/', health_check, name='health_check'),
    
    # Obfuscated Admin Portal to protect public URL
    path('admin-roc-portal-8f9a/', admin.site.urls),
    
    # V2 Parallel API Modules
    path('api/v2/auth/', include('apps.authentication.urls')),
    path('api/v2/profile/', include('apps.user_profiles.urls')),
]
