from django.db import models
from django.utils import timezone

class EmailQueueJobV2(models.Model):
    STATUS_CHOICES = [
        ('pending', 'Pending'),
        ('processing', 'Processing'),
        ('sent', 'Sent'),
        ('failed', 'Failed'),
    ]

    to_email = models.EmailField(max_length=255, db_index=True)
    subject = models.CharField(max_length=255)
    html_body = models.TextField()
    text_body = models.TextField(blank=True, default='')
    status = models.CharField(max_length=20, choices=STATUS_CHOICES, default='pending', db_index=True)
    attempts = models.PositiveIntegerField(default=0)
    locked_by = models.CharField(max_length=64, blank=True, null=True, db_index=True)
    locked_at = models.DateTimeField(blank=True, null=True)
    sent_at = models.DateTimeField(blank=True, null=True)
    error_message = models.CharField(max_length=255, blank=True, default='')
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'v2_email_queue'
        verbose_name = 'V2 Email Queue Job'
        verbose_name_plural = 'V2 Email Queue Jobs'
        indexes = [
            models.Index(fields=['status', 'locked_by'], name='idx_v2_eq_status_lock'),
        ]

    def __str__(self):
        return f"#{self.id} -> {self.to_email} ({self.status})"

class EmailEventV2(models.Model):
    queue_job = models.ForeignKey(EmailQueueJobV2, on_delete=models.CASCADE, related_name='events')
    event_type = models.CharField(max_length=50)
    details = models.CharField(max_length=255, blank=True, default='')
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = 'v2_email_events'
        verbose_name = 'V2 Email Event'
        verbose_name_plural = 'V2 Email Events'
