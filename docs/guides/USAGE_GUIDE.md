# 📚 How to Use the Custom Notification Transport

## 🚀 Quick Start Guide

This guide shows you exactly how to integrate and use the custom notification transport in your Symfony application for enhanced control and rich analytics.

## 📋 Prerequisites

- Symfony 7.0+ with Messenger component
- Doctrine ORM configured
- API Platform (optional, for monitoring endpoints)

## 🛠️ Installation & Setup

### 1. Install the Bundle

Add the bundle to your `config/bundles.php`:

```php
<?php
// config/bundles.php
return [
    // ... other bundles
    Nkamuo\NotificationTrackerBundle\NotificationTrackerBundle::class => ['all' => true],
];
```

### 2. Configure the Transport

Add to your `config/packages/messenger.yaml`:

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    failure_transport: failed
    
    transports:
      # Basic Email Transport
      notification_email:
        dsn: 'notification-tracking://doctrine?transport_name=email&analytics_enabled=true'
      
      # High Priority SMS with Provider Routing
      notification_sms_priority:
        dsn: 'notification-tracking://doctrine?transport_name=sms&queue_name=high_priority&provider_aware_routing=true&batch_size=5&max_retries=5'
      
      # Bulk Email with Custom Retry Strategy
      notification_bulk:
        dsn: 'notification-tracking://doctrine?transport_name=bulk&batch_size=50&retry_delays=1000,5000,30000,120000&analytics_enabled=false'
      
      # Failed messages transport
      failed: 'doctrine://default?queue_name=failed'

    routing:
      # Route your notification messages
      'App\Message\EmailNotification': notification_email
      'App\Message\SmsNotification': notification_sms_priority
      'App\Message\BulkEmailNotification': notification_bulk
```

### 3. Run Database Migration

Create and run the migration for the queue table:

```bash
# Generate migration
php bin/console doctrine:migrations:diff

# Or copy our pre-built migration from src/Migrations/
# Then run:
php bin/console doctrine:migrations:migrate
```

### 4. Configure Services (Optional)

If you need custom analytics collection:

```yaml
# config/services.yaml
services:
  # Custom analytics collector
  App\Service\CustomNotificationAnalytics:
    decorates: 'Nkamuo\NotificationTrackerBundle\Service\NotificationAnalyticsCollector'
    arguments:
      - '@App\Service\CustomNotificationAnalytics.inner'
```

## 🎯 Usage Examples

### Basic Message Dispatching

```php
<?php
// src/Controller/NotificationController.php

namespace App\Controller;

use App\Message\EmailNotification;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTemplateStamp;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class NotificationController extends AbstractController
{
    #[Route('/send-email', name: 'send_email')]
    public function sendEmail(MessageBusInterface $messageBus): Response
    {
        $message = new EmailNotification(
            to: 'user@example.com',
            subject: 'Welcome!',
            body: 'Welcome to our platform!'
        );

        // Dispatch with rich metadata
        $messageBus->dispatch($message, [
            new NotificationProviderStamp('email', 10),        // Provider + priority
            new NotificationCampaignStamp('welcome-series'),    // Campaign tracking
            new NotificationTemplateStamp('welcome-email')     // Template correlation
        ]);

        return $this->json(['status' => 'Email queued successfully']);
    }
}
```

### Advanced Usage with Campaign Tracking

```php
<?php
// src/Service/CampaignNotificationService.php

namespace App\Service;

use App\Message\EmailNotification;
use App\Message\SmsNotification;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTemplateStamp;
use Symfony\Component\Messenger\MessageBusInterface;

class CampaignNotificationService
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {}

    public function sendWelcomeCampaign(User $user): void
    {
        // Send welcome email with high priority
        $emailMessage = new EmailNotification(
            to: $user->getEmail(),
            subject: 'Welcome to Our Platform!',
            body: $this->renderWelcomeEmail($user)
        );

        $this->messageBus->dispatch($emailMessage, [
            new NotificationProviderStamp('email', 10),
            new NotificationCampaignStamp('welcome-2024'),
            new NotificationTemplateStamp('welcome-email', 'Welcome Email Template')
        ]);

        // Send SMS follow-up (lower priority)
        if ($user->getPhoneNumber()) {
            $smsMessage = new SmsNotification(
                to: $user->getPhoneNumber(),
                message: 'Welcome! Check your email for important details.'
            );

            $this->messageBus->dispatch($smsMessage, [
                new NotificationProviderStamp('sms', 5),
                new NotificationCampaignStamp('welcome-2024'),
                new NotificationTemplateStamp('welcome-sms', 'Welcome SMS Template')
            ]);
        }
    }

    public function sendBulkPromotion(array $users, string $promoCode): void
    {
        foreach ($users as $user) {
            $message = new BulkEmailNotification(
                to: $user->getEmail(),
                subject: "Special Offer: $promoCode",
                body: $this->renderPromoEmail($user, $promoCode)
            );

            // Use bulk transport with lower priority
            $this->messageBus->dispatch($message, [
                new NotificationProviderStamp('email', 1),
                new NotificationCampaignStamp('promo-' . date('Y-m')),
                new NotificationTemplateStamp('promo-email', 'Promotion Email Template')
            ]);
        }
    }
}
```

### Message Classes

```php
<?php
// src/Message/EmailNotification.php

namespace App\Message;

class EmailNotification
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $attachments = []
    ) {}
}
```

```php
<?php
// src/Message/SmsNotification.php

namespace App\Message;

class SmsNotification
{
    public function __construct(
        public readonly string $to,
        public readonly string $message
    ) {}
}
```

### Message Handlers

```php
<?php
// src/MessageHandler/EmailNotificationHandler.php

namespace App\MessageHandler;

use App\Message\EmailNotification;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class EmailNotificationHandler
{
    public function __construct(
        private MailerInterface $mailer
    ) {}

    public function __invoke(EmailNotification $message): void
    {
        $email = (new Email())
            ->to($message->to)
            ->subject($message->subject)
            ->html($message->body);

        $this->mailer->send($email);
        
        // The transport automatically handles analytics tracking
    }
}
```

## 📊 Monitoring & Analytics

### API Endpoints

Access real-time queue information:

```bash
# List all queued messages
curl http://your-app.com/api/queue/messages

# Filter by provider
curl http://your-app.com/api/queue/messages?notificationProvider=email

# Filter by status
curl http://your-app.com/api/queue/messages?status=pending

# Get queue statistics
curl http://your-app.com/api/queue/stats

# Check queue health
curl http://your-app.com/api/queue/health
```

### Custom Analytics Dashboard

```php
<?php
// src/Controller/AnalyticsController.php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\QueuedMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AnalyticsController extends AbstractController
{
    #[Route('/admin/notifications/analytics', name: 'notification_analytics')]
    public function analytics(EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(QueuedMessage::class);
        
        // Get statistics
        $stats = [
            'total_messages' => $repo->count([]),
            'pending_messages' => $repo->count(['status' => 'queued']),
            'processing_messages' => $repo->count(['status' => 'processing']),
            'processed_messages' => $repo->count(['status' => 'processed']),
            'failed_messages' => $repo->count(['status' => 'failed']),
        ];

        // Get provider breakdown
        $qb = $em->createQueryBuilder();
        $providerStats = $qb
            ->select('q.notificationProvider as provider, COUNT(q.id) as count')
            ->from(QueuedMessage::class, 'q')
            ->groupBy('q.notificationProvider')
            ->getQuery()
            ->getResult();

        // Get campaign performance
        $campaignStats = $qb
            ->select('q.campaignId as campaign, COUNT(q.id) as total, 
                     SUM(CASE WHEN q.status = \'processed\' THEN 1 ELSE 0 END) as processed')
            ->from(QueuedMessage::class, 'q')
            ->where('q.campaignId IS NOT NULL')
            ->groupBy('q.campaignId')
            ->getQuery()
            ->getResult();

        return $this->render('admin/notification_analytics.html.twig', [
            'stats' => $stats,
            'provider_stats' => $providerStats,
            'campaign_stats' => $campaignStats,
        ]);
    }
}
```

## ⚙️ Configuration Reference

### Complete DSN Parameters

```yaml
# notification-tracking://doctrine?param1=value1&param2=value2

# Available Parameters:
transport_name: string      # Transport identifier (default: 'default')
queue_name: string         # Internal queue name (default: 'default')
analytics_enabled: bool    # Enable analytics collection (default: true)
provider_aware_routing: bool # Route by provider (default: false)
batch_size: int            # Messages per batch, 1-100 (default: 10)
max_retries: int           # Max retry attempts, 0-10 (default: 3)
retry_delays: string       # Comma-separated delays in ms (default: '1000,5000,30000')
```

### Environment-Specific Configurations

```yaml
# config/packages/dev/messenger.yaml
framework:
  messenger:
    transports:
      notification_email:
        dsn: 'notification-tracking://doctrine?transport_name=email_dev&analytics_enabled=true&batch_size=1'

# config/packages/prod/messenger.yaml
framework:
  messenger:
    transports:
      notification_email:
        dsn: 'notification-tracking://doctrine?transport_name=email_prod&analytics_enabled=true&batch_size=25&max_retries=5'
```

## 🚀 Advanced Features

### Custom Provider Routing

```php
<?php
// Automatic routing based on provider stamps
$messageBus->dispatch($emailMessage, [
    new NotificationProviderStamp('email_priority', 10)  // Routes to email_priority queue
]);

$messageBus->dispatch($smsMessage, [
    new NotificationProviderStamp('sms_bulk', 1)         // Routes to sms_bulk queue
]);
```

### Delayed Messages

```php
<?php
use Symfony\Component\Messenger\Stamp\DelayStamp;

// Send email in 1 hour
$messageBus->dispatch($message, [
    new DelayStamp(3600000), // 1 hour in milliseconds
    new NotificationProviderStamp('email', 5)
]);
```

### Batch Processing

```php
<?php
// Process multiple messages efficiently
$messages = [];
foreach ($users as $user) {
    $messages[] = new Envelope(
        new EmailNotification($user->getEmail(), 'Subject', 'Body'),
        [new NotificationProviderStamp('email', 1)]
    );
}

// Send all at once (respects batch_size configuration)
$transport->send(...$messages);
```

## 🔧 Troubleshooting

### Common Issues

1. **Messages not being processed**
   ```bash
   # Check worker is running
   php bin/console messenger:consume notification_email -vv
   
   # Check queue status
   curl http://your-app.com/api/queue/health
   ```

2. **High memory usage**
   ```yaml
   # Reduce batch size
   dsn: 'notification-tracking://doctrine?batch_size=5'
   ```

3. **Too many retries**
   ```yaml
   # Adjust retry strategy
   dsn: 'notification-tracking://doctrine?max_retries=2&retry_delays=1000,10000'
   ```

### Performance Optimization

```yaml
# High-throughput configuration
notification_bulk:
  dsn: 'notification-tracking://doctrine?transport_name=bulk&batch_size=100&analytics_enabled=false&max_retries=1'

# High-reliability configuration  
notification_critical:
  dsn: 'notification-tracking://doctrine?transport_name=critical&batch_size=1&max_retries=10&retry_delays=500,2000,10000,60000'
```

## 📈 Production Deployment

### 1. Database Optimization

```sql
-- Add indexes for better performance
CREATE INDEX idx_notification_provider_status ON notification_queued_messages(notification_provider, status);
CREATE INDEX idx_campaign_status ON notification_queued_messages(campaign_id, status);
CREATE INDEX idx_created_at ON notification_queued_messages(created_at);
```

### 2. Worker Process

```bash
# Start worker processes
php bin/console messenger:consume notification_email notification_sms_priority --time-limit=3600 --memory-limit=256M

# Using Supervisor for production
# /etc/supervisor/conf.d/messenger-worker.conf
[program:messenger-consume]
command=php /path/to/your/app/bin/console messenger:consume notification_email notification_sms_priority --time-limit=3600 --memory-limit=256M
user=www-data
numprocs=2
startsecs=0
autostart=true
autorestart=true
process_name=%(program_name)s_%(process_num)02d
```

### 3. Monitoring Setup

```yaml
# config/packages/monolog.yaml
monolog:
  handlers:
    notification:
      type: stream
      path: "%kernel.logs_dir%/notification.log"
      level: info
      channels: ["notification"]
```

---

## 🎉 You're Ready!

Your custom notification transport is now fully configured and ready for production use! You have:

- ✅ **Enhanced Control** over message processing
- ✅ **Rich Analytics** for performance monitoring  
- ✅ **Provider-Aware Routing** for different notification types
- ✅ **Flexible Configuration** via DSN parameters
- ✅ **Real-time Monitoring** through API endpoints
- ✅ **Production-Ready** setup with comprehensive documentation

**¡Vamos!** 🚀 Start sending notifications with powerful analytics and control!
