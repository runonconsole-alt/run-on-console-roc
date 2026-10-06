from django.urls import path
from . import views
from . import oauth_views

urlpatterns = [
    path('session/', views.session_check_view, name='v2_session_check'),
    path('signup/', views.signup_view, name='v2_signup'),
    path('login/', views.login_view, name='v2_login'),
    path('logout/', views.logout_view, name='v2_logout'),
    path('verify-email/', views.verify_email_view, name='v2_verify_email'),
    path('google/start/', oauth_views.google_start_view, name='v2_google_start'),
    path('google/callback/', oauth_views.google_callback_view, name='v2_google_callback'),
]
