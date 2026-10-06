from django.urls import path
from . import views

urlpatterns = [
    path('', views.profile_detail_view, name='v2_profile_detail'),
    path('saved-products/', views.saved_products_view, name='v2_saved_products'),
    path('price-alerts/', views.price_alerts_view, name='v2_price_alerts'),
    path('export-data/', views.data_export_view, name='v2_data_export'),
    path('delete-account/', views.delete_account_view, name='v2_delete_account'),
]
