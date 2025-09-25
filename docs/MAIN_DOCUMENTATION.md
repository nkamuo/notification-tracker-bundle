# 🚀 Notification Tracker Bundle Documentation

> **⚠️ EXPERIMENTAL** - This bundle is in active development. Use only for testing and evaluation.

## 📋 Table of Contents

1. [**Quick Start**](#quick-start) - Get running in 5 minutes
2. [**How It Works**](#how-it-works) - Understanding the bundle
3. [**Installation**](#installation) - Step-by-step setup
4. [**Configuration**](#configuration) - Configure for your needs
5. [**Usage Examples**](#usage-examples) - Real-world code examples
6. [**API Reference**](#api-reference) - Complete API documentation
7. [**Advanced Topics**](#advanced-topics) - Deep dive features

---

## 🎯 Quick Start

This bundle **automatically tracks** notifications sent through Symfony's **Mailer** and **Notifier** components. No code changes required!

### What You Get Out of the Box

✅ **Automatic tracking** of all emails sent via Symfony Mailer  
✅ **Automatic tracking** of all notifications sent via Symfony Notifier  
✅ **REST API** to query notification history and analytics  
✅ **Real-time analytics** dashboard  
✅ **Webhook support** for delivery status updates  

### 5-Minute Setup

1. **Install the bundle:**
   ```bash
   composer require nkamuo/notification-tracker-bundle
   ```

2. **Add to bundles.php:**
   ```php
   Nkamuo\NotificationTrackerBundle\NotificationTrackerBundle::class => ['all' => true],
   ```

3. **Run migrations:**
   ```bash
   php bin/console doctrine:migrations:migrate
   ```

4. **Send an email as normal:**
   ```php
   // This is automatically tracked!
   $mailer->send($email);
   ```

5. **Check the API:**
   ```bash
   curl http://localhost/api/notification-tracker/messages
   ```

---

## 🔍 How It Works

### The Bundle is a **Passive Tracker**

This bundle doesn't replace Symfony Mailer or Notifier. Instead, it:

1. **Listens** to Symfony events when emails/notifications are sent
2. **Automatically captures** message details and stores them
3. **Provides APIs** to query the captured data
4. **Tracks delivery status** via webhooks (if configured)

### What Gets Tracked

| Component | What's Tracked | How |
|-----------|----------------|-----|
| **Symfony Mailer** | All emails sent via `$mailer->send()` | Event listeners |
| **Symfony Notifier** | All notifications via `$notifier->send()` | Event listeners |
| **Manual Creation** | Custom notifications via API | REST endpoints |

### Architecture Overview

```
Your App Code                    Bundle Components
┌─────────────────┐             ┌──────────────────┐
│ $mailer->send() │ ──events──► │ Event Listeners  │
│ $notifier->send()│             │ Database Storage │ 
└─────────────────┘             │ REST API         │
                                │ Analytics Engine │
                                └──────────────────┘
```

---

## 📦 Installation

### Requirements

- PHP 8.2+
- Symfony 7.0+
- Doctrine ORM
- API Platform (optional, for REST API)

### Step 1: Install via Composer

```bash
composer require nkamuo/notification-tracker-bundle
```

### Step 2: Enable the Bundle

Add to `config/bundles.php`:

```php
<?php
return [
    // ... other bundles
    Nkamuo\NotificationTrackerBundle\NotificationTrackerBundle::class => ['all' => true],
];
```

### Step 3: Configure Database

The bundle requires database tables. Run migrations:

```bash
# Generate migration (if needed)
php bin/console doctrine:migrations:diff

# Apply migrations
php bin/console doctrine:migrations:migrate
```

### Step 4: Configure API Platform (Optional)

For REST API access, ensure API Platform is configured:

```yaml
# config/packages/api_platform.yaml
api_platform:
    mapping:
        paths:
            - '%kernel.project_dir%/vendor/nkamuo/notification-tracker-bundle/src/Entity'
```

---

## ⚙️ Configuration

### Basic Configuration

No configuration required for basic tracking! The bundle works out of the box.

### Advanced Configuration (Optional)

```yaml
# config/packages/notification_tracker.yaml
notification_tracker:
    # Enable/disable automatic tracking
    auto_tracking:
        mailer: true      # Track Symfony Mailer emails
        notifier: true    # Track Symfony Notifier notifications
    
    # Configure webhooks for delivery tracking
    webhooks:
        providers:
            mailgun:
                endpoint: '/webhooks/mailgun'
                secret: '%env(MAILGUN_WEBHOOK_SECRET)%'
            sendgrid:
                endpoint: '/webhooks/sendgrid'
                secret: '%env(SENDGRID_WEBHOOK_SECRET)%'
    
    # Analytics settings
    analytics:
        retention_days: 90    # How long to keep analytics data
        cache_ttl: 300       # Cache analytics for 5 minutes
```

---

## 💡 Usage Examples

### Example 1: Normal Email Sending (Automatically Tracked)

```php
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    public function __construct(private MailerInterface $mailer) {}
    
    public function sendWelcomeEmail(string $to): void
    {
        $email = (new Email())
            ->to($to)
            ->subject('Welcome!')
            ->text('Welcome to our platform!');
        
        // This is automatically tracked by the bundle!
        $this->mailer->send($email);
    }
}
```

### Example 2: Normal Notification Sending (Automatically Tracked)

```php
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Notification\Notification;

class NotificationService
{
    public function __construct(private NotifierInterface $notifier) {}
    
    public function sendOrderUpdate(string $phone): void
    {
        $notification = (new Notification('Order shipped!'))
            ->content('Your order #1234 has been shipped.');
        
        // This is automatically tracked by the bundle!
        $this->notifier->send($notification, ['sms']);
    }
}
```

### Example 3: Querying Tracked Messages via API

```php
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MessageAnalytics 
{
    public function __construct(private HttpClientInterface $client) {}
    
    public function getRecentMessages(): array
    {
        $response = $this->client->request('GET', '/api/notification-tracker/messages', [
            'query' => [
                'createdAt[after]' => '2024-01-01',
                'status' => 'sent'
            ]
        ]);
        
        return $response->toArray();
    }
}
```

### Example 4: Manual Notification Creation via API

```php
// POST /api/notification-tracker/notifications
{
    "type": "order_confirmation",
    "subject": "Order Confirmed",
    "channels": ["email", "sms"],
    "context": {
        "order_id": "12345",
        "customer_name": "John Doe"
    },
    "recipients": [
        {
            "type": "email", 
            "value": "john@example.com"
        }
    ]
}
```

---

## 🌐 API Reference

### Messages API

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/notification-tracker/messages` | GET | List all tracked messages |
| `/api/notification-tracker/messages/{id}` | GET | Get specific message details |
| `/api/notification-tracker/messages/{id}/retry` | POST | Retry failed message |

### Notifications API  

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/notification-tracker/notifications` | GET | List all notifications |
| `/api/notification-tracker/notifications` | POST | Create new notification |
| `/api/notification-tracker/notifications/{id}` | GET | Get notification details |

### Analytics API

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/notification-tracker/analytics/dashboard` | GET | Dashboard overview |
| `/api/notification-tracker/analytics/channels` | GET | Channel performance |
| `/api/notification-tracker/analytics/trends` | GET | Time-based trends |

### Filtering Examples

```bash
# Get only failed messages
GET /api/notification-tracker/messages?status=failed

# Get emails only
GET /api/notification-tracker/messages?type=email

# Get messages from last week
GET /api/notification-tracker/messages?createdAt[after]=2024-01-01

# Exclude pending and failed messages (using custom filter)
GET /api/notification-tracker/messages?status[notin]=pending,failed

# Get messages not equal to email type
GET /api/notification-tracker/messages?type[ne]=email
```

---

## 🔧 Advanced Topics

### Webhook Configuration

For real-time delivery tracking, configure webhooks with your email provider:

```yaml
# Mailgun webhook example
notification_tracker:
    webhooks:
        providers:
            mailgun:
                endpoint: '/webhooks/mailgun'
                events: ['delivered', 'bounced', 'clicked']
```

### Custom Event Listeners

Extend tracking with custom listeners:

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Nkamuo\NotificationTrackerBundle\Event\MessageSentEvent;

class CustomTrackingSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [MessageSentEvent::class => 'onMessageSent'];
    }
    
    public function onMessageSent(MessageSentEvent $event): void
    {
        // Add custom tracking logic
    }
}
```

### Performance Optimization

For high-volume applications:

1. **Enable Redis caching** for analytics
2. **Configure database indexes** for frequently queried fields
3. **Set up async processing** for webhook handling
4. **Archive old data** to maintain performance

---

## 🚨 Important Notes

- **Experimental Status**: This bundle is in active development
- **Breaking Changes**: APIs may change without notice
- **Production Use**: Not recommended for production environments
- **Support**: Limited support available for experimental features

---

## 📞 Support & Contributing

- **Issues**: [GitHub Issues](https://github.com/nkamuo/notification-tracker-bundle/issues)
- **Email**: callistus@anvila.tech
- **Contributing**: Contributions welcome, but expect API changes

---

*Last Updated: September 2025*
