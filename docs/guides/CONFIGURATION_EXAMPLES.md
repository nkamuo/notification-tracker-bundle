# 🔧 Configuration Examples & Best Practices

This document provides real-world configuration examples and best practices for using the custom notification transport in different scenarios.

## 📋 Transport Configuration Patterns

### 1. Basic Email Setup
```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      notification_email:
        dsn: 'notification-tracking://doctrine?transport_name=email&analytics_enabled=true'
    
    routing:
      'App\Message\EmailNotification': notification_email
```

### 2. Multi-Provider Setup with Priorities
```yaml
framework:
  messenger:
    transports:
      # High priority email (immediate processing)
      email_priority:
        dsn: 'notification-tracking://doctrine?transport_name=email_high&queue_name=priority&batch_size=1&max_retries=5'
      
      # Regular email (batch processing)
      email_regular:
        dsn: 'notification-tracking://doctrine?transport_name=email_regular&batch_size=25&retry_delays=5000,30000,120000'
      
      # SMS notifications
      sms_notifications:
        dsn: 'notification-tracking://doctrine?transport_name=sms&provider_aware_routing=true&max_retries=3'
      
      # Push notifications
      push_notifications:
        dsn: 'notification-tracking://doctrine?transport_name=push&batch_size=50&analytics_enabled=true'
      
      # Bulk operations (high volume, low priority)
      bulk_email:
        dsn: 'notification-tracking://doctrine?transport_name=bulk&batch_size=100&analytics_enabled=false&max_retries=1'

    routing:
      'App\Message\HighPriorityEmail': email_priority
      'App\Message\RegularEmail': email_regular
      'App\Message\SmsNotification': sms_notifications
      'App\Message\PushNotification': push_notifications
      'App\Message\BulkEmail': bulk_email
```

### 3. Environment-Specific Configurations

#### Development Environment
```yaml
# config/packages/dev/messenger.yaml
framework:
  messenger:
    transports:
      notification_email:
        dsn: 'notification-tracking://doctrine?transport_name=email_dev&batch_size=1&analytics_enabled=true&max_retries=1'
      notification_sms:
        dsn: 'notification-tracking://doctrine?transport_name=sms_dev&batch_size=1&analytics_enabled=true'
```

#### Production Environment
```yaml
# config/packages/prod/messenger.yaml
framework:
  messenger:
    transports:
      notification_email:
        dsn: 'notification-tracking://doctrine?transport_name=email_prod&batch_size=25&analytics_enabled=true&max_retries=5&retry_delays=2000,10000,60000,300000'
      notification_sms:
        dsn: 'notification-tracking://doctrine?transport_name=sms_prod&batch_size=10&provider_aware_routing=true&max_retries=3'
```

## 🎯 Usage Patterns

### 1. Campaign-Based Notifications

```php
<?php
// src/Service/CampaignService.php

namespace App\Service;

use App\Message\EmailNotification;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTemplateStamp;
use Symfony\Component\Messenger\MessageBusInterface;

class CampaignService
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {}

    public function sendCampaign(string $campaignId, array $recipients, string $template): void
    {
        foreach ($recipients as $recipient) {
            $message = new EmailNotification(
                to: $recipient['email'],
                subject: $recipient['subject'],
                body: $this->renderTemplate($template, $recipient)
            );

            $this->messageBus->dispatch($message, [
                new NotificationProviderStamp('email', $recipient['priority'] ?? 5),
                new NotificationCampaignStamp($campaignId),
                new NotificationTemplateStamp($template)
            ]);
        }
    }

    public function sendTransactionalEmail(string $userEmail, string $type, array $data): void
    {
        $templates = [
            'welcome' => ['template' => 'welcome-email', 'priority' => 10],
            'reset_password' => ['template' => 'password-reset', 'priority' => 10],
            'invoice' => ['template' => 'invoice-email', 'priority' => 8],
            'newsletter' => ['template' => 'newsletter', 'priority' => 3],
        ];

        $config = $templates[$type] ?? ['template' => 'default', 'priority' => 5];

        $message = new EmailNotification(
            to: $userEmail,
            subject: $data['subject'],
            body: $this->renderTemplate($config['template'], $data)
        );

        $this->messageBus->dispatch($message, [
            new NotificationProviderStamp('email', $config['priority']),
            new NotificationCampaignStamp("transactional-$type"),
            new NotificationTemplateStamp($config['template'])
        ]);
    }
}
```

### 2. Multi-Channel Notifications

```php
<?php
// src/Service/NotificationOrchestrator.php

namespace App\Service;

use App\Message\EmailNotification;
use App\Message\PushNotification;
use App\Message\SmsNotification;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationProviderStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class NotificationOrchestrator
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {}

    public function sendOrderConfirmation(Order $order): void
    {
        $user = $order->getUser();
        $campaignId = "order-confirmation-{$order->getId()}";

        // 1. Immediate email confirmation
        $emailMessage = new EmailNotification(
            to: $user->getEmail(),
            subject: "Order Confirmation #{$order->getOrderNumber()}",
            body: $this->renderOrderConfirmation($order)
        );

        $this->messageBus->dispatch($emailMessage, [
            new NotificationProviderStamp('email', 10),
            new NotificationCampaignStamp($campaignId)
        ]);

        // 2. SMS notification (if enabled)
        if ($user->getSmsEnabled() && $user->getPhoneNumber()) {
            $smsMessage = new SmsNotification(
                to: $user->getPhoneNumber(),
                message: "Your order #{$order->getOrderNumber()} has been confirmed. Check your email for details."
            );

            $this->messageBus->dispatch($smsMessage, [
                new NotificationProviderStamp('sms', 8),
                new NotificationCampaignStamp($campaignId),
                new DelayStamp(60000) // 1 minute delay
            ]);
        }

        // 3. Push notification (if mobile app user)
        if ($user->getPushTokens()) {
            $pushMessage = new PushNotification(
                tokens: $user->getPushTokens(),
                title: 'Order Confirmed!',
                body: "Order #{$order->getOrderNumber()} is being prepared"
            );

            $this->messageBus->dispatch($pushMessage, [
                new NotificationProviderStamp('push', 7),
                new NotificationCampaignStamp($campaignId),
                new DelayStamp(120000) // 2 minutes delay
            ]);
        }

        // 4. Follow-up email with tracking info (after processing)
        $trackingEmail = new EmailNotification(
            to: $user->getEmail(),
            subject: "Your order is on its way!",
            body: $this->renderTrackingEmail($order)
        );

        $this->messageBus->dispatch($trackingEmail, [
            new NotificationProviderStamp('email', 5),
            new NotificationCampaignStamp($campaignId),
            new DelayStamp(3600000) // 1 hour delay
        ]);
    }
}
```

### 3. Bulk Operations with Analytics

```php
<?php
// src/Service/BulkNotificationService.php

namespace App\Service;

use App\Message\BulkEmailNotification;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationProviderStamp;
use Symfony\Component\Messenger\MessageBusInterface;

class BulkNotificationService
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {}

    public function sendNewsletterCampaign(array $subscribers, string $content): string
    {
        $campaignId = 'newsletter-' . date('Y-m-d-H-i-s');
        $batchSize = 1000;
        $priority = 2; // Low priority for bulk

        $batches = array_chunk($subscribers, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            foreach ($batch as $subscriber) {
                $message = new BulkEmailNotification(
                    to: $subscriber['email'],
                    subject: $subscriber['personalized_subject'],
                    body: $this->personalizeContent($content, $subscriber)
                );

                $this->messageBus->dispatch($message, [
                    new NotificationProviderStamp('email', $priority),
                    new NotificationCampaignStamp($campaignId)
                ]);
            }

            // Add delay between batches to prevent overwhelming
            if ($batchIndex < count($batches) - 1) {
                sleep(5);
            }
        }

        return $campaignId;
    }

    public function sendSegmentedPromotion(array $segments): array
    {
        $campaignIds = [];

        foreach ($segments as $segmentName => $segment) {
            $campaignId = "promo-{$segmentName}-" . date('Y-m-d');
            $campaignIds[$segmentName] = $campaignId;

            // Different priorities based on segment value
            $priority = match($segment['tier']) {
                'premium' => 6,
                'regular' => 4,
                'trial' => 2,
                default => 3
            };

            foreach ($segment['users'] as $user) {
                $message = new BulkEmailNotification(
                    to: $user['email'],
                    subject: $segment['subject'],
                    body: $this->renderPromoEmail($user, $segment)
                );

                $this->messageBus->dispatch($message, [
                    new NotificationProviderStamp('email', $priority),
                    new NotificationCampaignStamp($campaignId)
                ]);
            }
        }

        return $campaignIds;
    }
}
```

## 📊 Monitoring & Analytics Integration

### 1. Custom Analytics Service

```php
<?php
// src/Service/NotificationAnalyticsService.php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\QueuedMessage;

class NotificationAnalyticsService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function getCampaignPerformance(string $campaignId): array
    {
        $repo = $this->em->getRepository(QueuedMessage::class);

        $qb = $this->em->createQueryBuilder();
        $stats = $qb
            ->select('
                COUNT(q.id) as total,
                SUM(CASE WHEN q.status = \'processed\' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN q.status = \'failed\' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN q.status IN (\'queued\', \'processing\') THEN 1 ELSE 0 END) as pending,
                AVG(CASE WHEN q.processedAt IS NOT NULL THEN 
                    TIMESTAMPDIFF(SECOND, q.createdAt, q.processedAt) 
                END) as avgProcessingTime
            ')
            ->from(QueuedMessage::class, 'q')
            ->where('q.campaignId = :campaignId')
            ->setParameter('campaignId', $campaignId)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'campaign_id' => $campaignId,
            'total_messages' => (int) $stats['total'],
            'delivered' => (int) $stats['delivered'],
            'failed' => (int) $stats['failed'],
            'pending' => (int) $stats['pending'],
            'delivery_rate' => $stats['total'] > 0 ? ($stats['delivered'] / $stats['total']) * 100 : 0,
            'average_processing_time' => $stats['avgProcessingTime'] ? round($stats['avgProcessingTime'], 2) : null
        ];
    }

    public function getProviderPerformance(): array
    {
        $qb = $this->em->createQueryBuilder();
        return $qb
            ->select('
                q.notificationProvider as provider,
                COUNT(q.id) as total,
                SUM(CASE WHEN q.status = \'processed\' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN q.status = \'failed\' THEN 1 ELSE 0 END) as failed,
                AVG(CASE WHEN q.processedAt IS NOT NULL THEN 
                    TIMESTAMPDIFF(SECOND, q.createdAt, q.processedAt) 
                END) as avgProcessingTime
            ')
            ->from(QueuedMessage::class, 'q')
            ->where('q.notificationProvider IS NOT NULL')
            ->groupBy('q.notificationProvider')
            ->getQuery()
            ->getResult();
    }

    public function getHourlyVolume(\DateTimeInterface $date): array
    {
        $qb = $this->em->createQueryBuilder();
        return $qb
            ->select('
                HOUR(q.createdAt) as hour,
                COUNT(q.id) as count
            ')
            ->from(QueuedMessage::class, 'q')
            ->where('DATE(q.createdAt) = DATE(:date)')
            ->setParameter('date', $date)
            ->groupBy('hour')
            ->orderBy('hour')
            ->getQuery()
            ->getResult();
    }
}
```

### 2. Real-time Dashboard Controller

```php
<?php
// src/Controller/Admin/NotificationDashboardController.php

namespace App\Controller\Admin;

use App\Service\NotificationAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/notifications')]
class NotificationDashboardController extends AbstractController
{
    public function __construct(
        private NotificationAnalyticsService $analytics
    ) {}

    #[Route('/dashboard', name: 'notification_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/notifications/dashboard.html.twig');
    }

    #[Route('/api/stats', name: 'notification_stats_api')]
    public function getStats(): JsonResponse
    {
        return $this->json([
            'provider_performance' => $this->analytics->getProviderPerformance(),
            'hourly_volume' => $this->analytics->getHourlyVolume(new \DateTime()),
            'queue_health' => $this->getQueueHealth()
        ]);
    }

    #[Route('/api/campaign/{campaignId}', name: 'campaign_stats_api')]
    public function getCampaignStats(string $campaignId): JsonResponse
    {
        return $this->json(
            $this->analytics->getCampaignPerformance($campaignId)
        );
    }

    private function getQueueHealth(): array
    {
        // Call the queue health endpoint
        // This would typically be done via HTTP client to your API
        return [
            'status' => 'healthy',
            'pending_messages' => 42,
            'processing_messages' => 3,
            'failed_messages' => 1
        ];
    }
}
```

## 🔧 Performance Optimization

### 1. Worker Configuration

```bash
#!/bin/bash
# scripts/start-workers.sh

# High priority workers (more processes)
php bin/console messenger:consume email_priority sms_notifications --time-limit=3600 --memory-limit=256M --limit=100 &
php bin/console messenger:consume email_priority sms_notifications --time-limit=3600 --memory-limit=256M --limit=100 &

# Regular priority workers
php bin/console messenger:consume email_regular push_notifications --time-limit=3600 --memory-limit=512M --limit=500 &
php bin/console messenger:consume email_regular push_notifications --time-limit=3600 --memory-limit=512M --limit=500 &

# Bulk processing worker (single process, high throughput)
php bin/console messenger:consume bulk_email --time-limit=3600 --memory-limit=1G --limit=1000 &

wait
```

### 2. Database Optimization

```sql
-- Additional indexes for better performance
CREATE INDEX idx_queued_messages_provider_priority ON notification_queued_messages(notification_provider, priority DESC);
CREATE INDEX idx_queued_messages_campaign_status ON notification_queued_messages(campaign_id, status);
CREATE INDEX idx_queued_messages_created_status ON notification_queued_messages(created_at, status);
CREATE INDEX idx_queued_messages_available_transport ON notification_queued_messages(available_at, transport) WHERE status IN ('queued', 'processing');

-- Partitioning for large volumes (PostgreSQL example)
CREATE TABLE notification_queued_messages_2024_01 PARTITION OF notification_queued_messages
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');
```

### 3. Caching Strategy

```yaml
# config/packages/cache.yaml
framework:
  cache:
    pools:
      notification.analytics:
        adapter: cache.adapter.redis
        default_lifetime: 3600 # 1 hour
        
      notification.templates:
        adapter: cache.adapter.redis
        default_lifetime: 86400 # 24 hours
```

```php
<?php
// Cached analytics service
namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CachedNotificationAnalyticsService
{
    public function __construct(
        private NotificationAnalyticsService $analytics,
        private CacheInterface $analyticsCache
    ) {}

    public function getCampaignPerformance(string $campaignId): array
    {
        return $this->analyticsCache->get(
            "campaign_performance_{$campaignId}",
            function (ItemInterface $item) use ($campaignId) {
                $item->expiresAfter(300); // 5 minutes
                return $this->analytics->getCampaignPerformance($campaignId);
            }
        );
    }
}
```

## 🚀 Production Deployment Checklist

### Pre-Deployment
- [ ] Database migration completed
- [ ] Indexes created for performance
- [ ] Worker processes configured
- [ ] Monitoring endpoints tested
- [ ] Analytics dashboards working
- [ ] Error logging configured

### Configuration Validation
- [ ] Transport DSN parameters verified
- [ ] Routing rules tested
- [ ] Retry strategies appropriate
- [ ] Batch sizes optimized
- [ ] Analytics collection enabled

### Monitoring Setup
- [ ] Queue health checks automated
- [ ] Performance metrics tracked
- [ ] Alert thresholds configured
- [ ] Log aggregation working
- [ ] Dashboard accessible

Your custom notification transport is now fully documented and ready for any scale of deployment! 🎉
