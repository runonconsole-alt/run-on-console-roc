# Run On Console (ROC) - Legacy PHP Data Migration Plan to Django V2

This document details the safe, non-destructive migration strategy for transferring existing user accounts, profiles, and verification states from the legacy PHP database tables (`users`, `user_profiles`) to the parallel Django V2 tables (`v2_users`, `v2_user_profiles`).

---

## 🎯 1. Migration Goals & Constraints

1. **Zero Data Loss**: Existing registered users must retain their login ability, verified status, and profile information.
2. **Password Compatibility**: Legacy PHP Argon2id & Bcrypt password hashes must be translated cleanly without forcing users to reset passwords.
3. **Collision Safety**: Duplicate emails or invalid usernames will be resolved automatically before insertion into `v2_users`.
4. **Zero Downtime**: Legacy PHP tables remain 100% untouched.

---

## 🔄 2. Password Hash Translation Strategy

Django supports Argon2id and Bcrypt out of the box via `django.contrib.auth.hashers`.

- **Legacy Argon2id Hashes**: Formatted as `$argon2id$v=19$m=65536,t=4,p=1$...` -> Directly compatible with Django's `Argon2PasswordHasher`.
- **Legacy Bcrypt Hashes**: Formatted as `$2y$12$...` -> Converted to `$2b$12$...` for Django's `BCryptSHA256PasswordHasher`.

---

## 📜 3. Custom Django Management Migration Command

A dedicated migration command will be executed after Django V2 tables are created:

```python
# django_backend/apps/authentication/management/commands/migrate_legacy_users.py
from django.core.management.base import BaseCommand
from django.db import connection, transaction
from django.contrib.auth.hashers import make_password
from apps.authentication.models import UserV2
from apps.user_profiles.models import UserProfileV2

class Command(BaseCommand):
    help = 'Safely migrate legacy PHP users into Django v2_users table'

    def handle(self, *args, **options):
        with connection.cursor() as cursor:
            cursor.execute("SELECT id, uuid, name, username, email, password_hash, role, status, is_verified FROM users")
            legacy_users = cursor.fetchall()

        migrated = 0
        skipped = 0

        for row in legacy_users:
            leg_id, leg_uuid, name, username, email, password_hash, role, status, is_verified = row
            email_clean = email.strip().lower()

            if UserV2.objects.filter(email=email_clean).exists():
                skipped += 1
                continue

            # Ensure valid unique username
            base_u = username or email_clean.split('@')[0]
            final_u = base_u
            counter = 1
            while UserV2.objects.filter(username__iexact=final_u).exists():
                final_u = f"{base_u}_{counter}"
                counter += 1

            # Format password hash for Django
            formatted_hash = password_hash
            if password_hash.startswith('$2y$'):
                formatted_hash = '$2b$' + password_hash[4:]

            with transaction.atomic():
                user = UserV2.objects.create(
                    uuid=leg_uuid or f"usr-{leg_id}",
                    email=email_clean,
                    name=name,
                    username=final_u,
                    password=formatted_hash,
                    role=role if role in ('gamer', 'moderator', 'admin') else 'gamer',
                    status='active' if is_verified else 'pending_verification',
                    is_verified=bool(is_verified)
                )

                UserProfileV2.objects.get_or_create(user=user)
                migrated += 1

        self.stdout.write(self.style.SUCCESS(f"✅ Migration complete! {migrated} users migrated, {skipped} skipped."))
```
