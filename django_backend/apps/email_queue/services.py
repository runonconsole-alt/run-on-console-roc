from django.utils.html import strip_tags
from .models import EmailQueueJobV2

def queue_email(to_email, subject, html_body, text_body=None):
    """
    Safely enqueue an outgoing email into the database queue.
    """
    if text_body is None:
        text_body = strip_tags(html_body)

    try:
        job = EmailQueueJobV2.objects.create(
            to_email=to_email,
            subject=subject,
            html_body=html_body,
            text_body=text_body,
            status='pending'
        )
        return job.id
    except Exception as e:
        return None
