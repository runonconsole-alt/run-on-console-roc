import uuid
from django.db import models
from django.contrib.auth.models import AbstractBaseUser, BaseUserManager, PermissionsMixin
from django.utils import timezone

class UserV2Manager(BaseUserManager):
    def create_user(self, email, name, username=None, password=None, **extra_fields):
        if not email:
            raise ValueError('Email address is required')
        email = self.normalize_email(email).lower().strip()
        user = self.model(email=email, name=name, username=username, **extra_fields)
        if password:
            user.set_password(password)
        else:
            user.set_unusable_password()
        user.save(using=self._db)
        return user

    def create_superuser(self, email, name, password=None, **extra_fields):
        extra_fields.setdefault('is_staff', True)
        extra_fields.setdefault('is_superuser', True)
        extra_fields.setdefault('role', 'admin')
        extra_fields.setdefault('status', 'active')
        extra_fields.setdefault('is_verified', True)

        return self.create_user(email, name, password=password, **extra_fields)

class UserV2(AbstractBaseUser, PermissionsMixin):
    ROLE_CHOICES = [
        ('gamer', 'Gamer Member'),
        ('moderator', 'Moderator'),
        ('admin', 'Administrator'),
    ]

    STATUS_CHOICES = [
        ('pending_verification', 'Pending Verification'),
        ('active', 'Active'),
        ('suspended', 'Suspended'),
    ]

    uuid = models.CharField(max_length=64, unique=True, default=uuid.uuid4, db_index=True)
    name = models.CharField(max_length=150)
    username = models.CharField(max_length=50, unique=True, db_index=True)
    email = models.EmailField(max_length=255, unique=True, db_index=True)
    role = models.CharField(max_length=20, choices=ROLE_CHOICES, default='gamer')
    status = models.CharField(max_length=30, choices=STATUS_CHOICES, default='pending_verification')
    is_verified = models.BooleanField(default=False)
    
    is_staff = models.BooleanField(default=False)
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    objects = UserV2Manager()

    USERNAME_FIELD = 'email'
    REQUIRED_FIELDS = ['name']

    class Meta:
        db_table = 'v2_users'
        verbose_name = 'V2 User'
        verbose_name_plural = 'V2 Users'

    def __str__(self):
        return f"{self.name} ({self.email})"

class EmailVerificationChallengeV2(models.Model):
    user = models.ForeignKey(UserV2, on_delete=models.CASCADE, related_name='verification_challenges')
    code_hash = models.CharField(max_length=255)
    link_token_hash = models.CharField(max_length=255, db_index=True)
    attempts = models.PositiveIntegerField(default=0)
    expires_at = models.DateTimeField()
    consumed_at = models.DateTimeField(blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = 'v2_email_challenges'
        verbose_name = 'V2 Email Challenge'
        verbose_name_plural = 'V2 Email Challenges'

    def is_valid(self):
        return self.consumed_at is None and timezone.now() < self.expires_at

class PasswordResetTokenV2(models.Model):
    user = models.ForeignKey(UserV2, on_delete=models.CASCADE, related_name='password_reset_tokens')
    token_hash = models.CharField(max_length=255, db_index=True)
    expires_at = models.DateTimeField()
    used_at = models.DateTimeField(blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = 'v2_password_reset_tokens'
        verbose_name = 'V2 Password Reset Token'
        verbose_name_plural = 'V2 Password Reset Tokens'

class RateLimitRecordV2(models.Model):
    ip_address = models.CharField(max_length=45, db_index=True)
    email_hash = models.CharField(max_length=64, db_index=True)
    action = models.CharField(max_length=50, db_index=True)
    attempted_at = models.DateTimeField(auto_now_add=True, db_index=True)

    class Meta:
        db_table = 'v2_rate_limits'
        verbose_name = 'V2 Rate Limit Record'
        verbose_name_plural = 'V2 Rate Limit Records'

class OAuthAccountLinkV2(models.Model):
    user = models.ForeignKey(UserV2, on_delete=models.CASCADE, related_name='oauth_accounts')
    provider = models.CharField(max_length=50, default='google')
    provider_user_id = models.CharField(max_length=255, db_index=True)
    provider_email = models.EmailField(max_length=255)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = 'v2_oauth_accounts'
        unique_together = ('provider', 'provider_user_id')
        verbose_name = 'V2 OAuth Account Link'
        verbose_name_plural = 'V2 OAuth Account Links'
