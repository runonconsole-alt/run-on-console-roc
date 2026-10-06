import re
from django.contrib.auth import get_user_model

UserV2 = get_user_model()

def validate_username_format(username):
    if not username or len(username) < 3 or len(username) > 30:
        return False, "Username must be between 3 and 30 characters long."
    
    if not re.match(r'^[a-zA-Z0-9_]+$', username):
        return False, "Username may only contain letters, numbers, and underscores."

    reserved = ['admin', 'administrator', 'support', 'root', 'runonconsole', 'system', 'moderator', 'guest', 'api', 'help']
    if username.lower() in reserved:
        return False, "This username is reserved and cannot be registered."

    if UserV2.objects.filter(username__iexact=username).exists():
        return False, "This username is already taken by another gamer."

    return True, "Valid"
