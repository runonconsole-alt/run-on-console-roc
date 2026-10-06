import uuid
import sys
from datetime import timedelta
from django.core.management.base import BaseCommand
from django.utils import timezone
from django.db import transaction
from django.core.mail import EmailMultiAlternatives
from django.conf import settings
from apps.email_queue.models import EmailQueueJobV2, EmailEventV2

class Command(BaseCommand):
    help = 'Process pending emails in database queue for Run On Console (v2)'

    def handle(self, *args, **options):
        # 1. Recover Abandoned Jobs (processing > 15 mins)
        cutoff = timezone.now() - timedelta(minutes=15)
        abandoned_updated = EmailQueueJobV2.objects.filter(
            status='processing',
            locked_at__lt=cutoff,
            attempts__lt=5
        ).update(status='pending', locked_by=None, locked_at=None)

        if abandoned_updated > 0:
            self.stdout.write(self.style.WARNING(f"Recovered {abandoned_updated} abandoned email jobs."))

        # 2. Claim Pending Jobs Atomically
        worker_id = f"worker_{uuid.uuid4().hex[:12]}"
        now = timezone.now()

        with transaction.atomic():
            pending_ids = list(
                EmailQueueJobV2.objects.filter(status='pending', attempts__lt=5)
                .order_by('created_at')
                .values_list('id', flat=True)[:20]
            )

            if not pending_ids:
                self.stdout.write("No pending email jobs to process.")
                return

            EmailQueueJobV2.objects.filter(id__in=pending_ids).update(
                status='processing',
                locked_by=worker_id,
                locked_at=now
            )

        # 3. Process Claimed Jobs
        jobs = EmailQueueJobV2.objects.filter(status='processing', locked_by=worker_id)

        for job in jobs:
            job.attempts += 1
            job.save(update_fields=['attempts'])

            try:
                msg = EmailMultiAlternatives(
                    subject=job.subject,
                    body=job.text_body or "Please view this email in an HTML-compatible client.",
                    from_email=settings.DEFAULT_FROM_EMAIL,
                    to=[job.to_email],
                    reply_to=[getattr(settings, 'MAIL_REPLY_TO', 'support@runonconsole.com')]
                )
                msg.attach_alternative(job.html_body, "text/html")
                msg.send(fail_silently=False)

                job.status = 'sent'
                job.sent_at = timezone.now()
                job.locked_by = None
                job.locked_at = None
                job.error_message = ''
                job.save(update_fields=['status', 'sent_at', 'locked_by', 'locked_at', 'error_message'])

                EmailEventV2.objects.create(
                    queue_job=job,
                    event_type='sent',
                    details=f"Dispatched successfully to {job.to_email[:3]}***@{job.to_email.split('@')[-1]}"
                )

                masked = f"{job.to_email[:3]}***@{job.to_email.split('@')[-1]}"
                self.stdout.write(self.style.SUCCESS(f"✅ Sent email #{job.id} to {masked}"))

            except Exception as e:
                err_msg = str(e)[:255]
                new_status = 'failed' if job.attempts >= 5 else 'pending'
                job.status = new_status
                job.locked_by = None
                job.locked_at = None
                job.error_message = err_msg
                job.save(update_fields=['status', 'locked_by', 'locked_at', 'error_message'])

                EmailEventV2.objects.create(
                    queue_job=job,
                    event_type='failed',
                    details=err_msg
                )

                self.stderr.write(self.style.ERROR(f"❌ Failed email #{job.id} (Attempt {job.attempts}): {err_msg}"))
