from django.db import models
from django.conf import settings

class UserProfileV2(models.Model):
    user = models.OneToOneField(settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name='profile_v2')
    country = models.CharField(max_length=100, default='United States')
    currency = models.CharField(max_length=10, default='USD')
    avatar_icon = models.CharField(max_length=50, default='gamepad')
    avatar_bg = models.CharField(max_length=100, default='from-emerald-600 to-teal-500')
    bio = models.TextField(blank=True, default='Gamer & hardware enthusiast on Run On Console.')
    marketing_opt_in = models.BooleanField(default=False)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'v2_user_profiles'
        verbose_name = 'V2 User Profile'
        verbose_name_plural = 'V2 User Profiles'

    def __str__(self):
        return f"Profile for {self.user.email}"

class SavedProductV2(models.Model):
    user = models.ForeignKey(settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name='saved_products_v2')
    product_slug = models.CharField(max_length=255, db_index=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = 'v2_saved_products'
        unique_together = ('user', 'product_slug')
        verbose_name = 'V2 Saved Product'
        verbose_name_plural = 'V2 Saved Products'

class PriceAlertV2(models.Model):
    user = models.ForeignKey(settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name='price_alerts_v2')
    product_slug = models.CharField(max_length=255, db_index=True)
    target_price = models.DecimalField(max_digits=10, decimal_places=2)
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = 'v2_price_alerts'
        verbose_name = 'V2 Price Alert'
        verbose_name_plural = 'V2 Price Alerts'

class UserPreferenceV2(models.Model):
    user = models.OneToOneField(settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name='preference_v2')
    dark_mode = models.BooleanField(default=True)
    alert_frequency = models.CharField(max_length=20, default='instant')
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = 'v2_user_preferences'

class CommentHistoryV2(models.Model):
    user = models.ForeignKey(settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name='comments_v2')
    page_slug = models.CharField(max_length=255, db_index=True)
    comment_text = models.TextField()
    is_approved = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = 'v2_comment_history'
