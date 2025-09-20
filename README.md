# 📚 Custom Notification Transport Documentation

Welcome to the **Custom Notification Transport** documentation! This transport provides enhanced control and rich analytics for Symfony Messenger notifications.

## 📋 Documentation Overview

### 🚀 [Quick Start Guide](USAGE_GUIDE.md)
Complete step-by-step guide to integrate and use the transport in your application.

**What you'll learn:**
- Installation and setup
- Basic configuration
- Message dispatching with stamps
- Monitoring and analytics

### 🔧 [Configuration Examples](CONFIGURATION_EXAMPLES.md)
Real-world configuration patterns and best practices.

**What you'll learn:**
- Multi-provider setups
- Environment-specific configurations
- Performance optimization
- Advanced usage patterns

### ✅ [Test Documentation](TESTS_COMPLETE.md)
Complete test results and validation summary.

**What you'll learn:**
- Test coverage details
- Validation scenarios
- Performance benchmarks
- Production readiness

### 🏗️ [Implementation Summary](TRANSPORT_COMPLETE.md)
Technical overview of the complete implementation.

**What you'll learn:**
- Architecture decisions
- Feature overview
- API endpoints
- Integration points

## 🎯 Key Features

### Enhanced Message Control
- **Provider-Aware Routing**: Route messages by notification type (email, SMS, push)
- **Priority-Based Processing**: Control message processing order
- **Batch Processing**: Efficient handling of high-volume operations
- **Custom Retry Strategies**: Configurable retry logic with exponential backoff

### Rich Analytics & Monitoring
- **Real-time Queue Statistics**: Live monitoring of message processing
- **Campaign Performance Tracking**: Track success rates by campaign
- **Provider Performance Metrics**: Compare different notification channels
- **API Platform Integration**: RESTful endpoints for monitoring

### Flexible Configuration
- **DSN-Based Setup**: Configure via connection strings with query parameters
- **Environment-Specific**: Different configs for dev/staging/production
- **Validation & Security**: Comprehensive input validation and error handling

## 🚀 Quick Reference

### Basic DSN Configuration
```yaml
framework:
  messenger:
    transports:
      notification_email:
        dsn: 'notification-tracking://doctrine?transport_name=email&analytics_enabled=true'
```

### Message Dispatching
```php
$messageBus->dispatch($message, [
    new NotificationProviderStamp('email', 10),        // Provider + priority
    new NotificationCampaignStamp('welcome-series'),    // Campaign tracking
    new NotificationTemplateStamp('welcome-email')     // Template correlation
]);
```

### API Endpoints
- `GET /api/queue/messages` - List queued messages
- `GET /api/queue/stats` - Queue analytics
- `GET /api/queue/health` - Health monitoring

## 📊 DSN Parameters Reference

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `transport_name` | string | 'default' | Transport identifier |
| `queue_name` | string | 'default' | Internal queue name |
| `analytics_enabled` | boolean | true | Enable analytics collection |
| `provider_aware_routing` | boolean | false | Route by notification provider |
| `batch_size` | integer (1-100) | 10 | Messages processed per batch |
| `max_retries` | integer (0-10) | 3 | Maximum retry attempts |
| `retry_delays` | string | '1000,5000,30000' | Comma-separated delays in ms |

## 🔗 External Links

- [Symfony Messenger Documentation](https://symfony.com/doc/current/messenger.html)
- [API Platform Documentation](https://api-platform.com/docs/)
- [Doctrine ORM Documentation](https://www.doctrine-project.org/projects/orm.html)

## 🤝 Support

### Common Use Cases
1. **E-commerce Notifications**: Order confirmations, shipping updates, promotional emails
2. **User Engagement**: Welcome series, re-engagement campaigns, newsletters
3. **Transactional Messages**: Password resets, account verification, billing notifications
4. **Multi-channel Marketing**: Coordinated email, SMS, and push campaigns

### Performance Guidelines
- **Small Volume** (< 1K/hour): Default settings work well
- **Medium Volume** (1K-10K/hour): Increase batch_size to 25-50
- **High Volume** (> 10K/hour): Use bulk transport with batch_size=100, analytics_enabled=false

### Troubleshooting
- Check worker processes are running
- Monitor queue health endpoints
- Review error logs for failed messages
- Validate DSN configuration parameters

---

## 🎉 Ready to Get Started?

1. **First Time Users**: Start with the [Usage Guide](USAGE_GUIDE.md)
2. **Advanced Setup**: Check [Configuration Examples](CONFIGURATION_EXAMPLES.md)
3. **Validation**: Review [Test Results](TESTS_COMPLETE.md)

**Your enhanced notification system with rich analytics is ready to deploy!** 🚀

## Features

- 📧 **Multi-channel Support**: Email, SMS, Slack, Telegram, Push notifications
- 🔄 **Native Symfony Integration**: Works seamlessly with Symfony Mailer and Notifier events
- 📊 **Complete Tracking**: Track sent, delivered, opened, clicked, bounced, and failed messages
- 🔗 **Webhook Support**: Integrate with SendGrid, Twilio, Mailgun, Mailchimp, and more
- 📎 **File Attachments**: Handle and track message attachments
- 🎨 **Template System**: Manage reusable message templates
- 🚀 **Async Processing**: Built-in Symfony Messenger support
- 🔍 **API Platform Integration**: Rich REST/GraphQL API for browsing and managing notifications
- 📈 **Analytics Dashboard**: Real-time and cached analytics
- ⚙️ **Highly Configurable**: Environment-based configuration with sensible defaults
- 🔐 **Security**: Webhook signature verification and IP whitelisting

## Installation

```bash
composer require nkamuo/notification-tracker-bundle
```

### Register the Bundle

If not using Symfony Flex, register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Nkamuo\NotificationTrackerBundle\NotificationTrackerBundle::class => ['all' => true],
];
```

### Run Migrations

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Configuration

### Basic Configuration

Create `config/packages/notification_tracker.yaml`:

```yaml
notification_tracker:
    enabled: true
    tracking:
        enabled: true
        track_opens: true
        track_clicks: true
```

### Environment Variables

Copy the `.env.dist` entries to your `.env.local` and configure:

```bash
NOTIFICATION_TRACKER_ENABLED=true
SENDGRID_WEBHOOK_SECRET=your_secret
TWILIO_AUTH_TOKEN=your_token
# ... other provider credentials
```

## Usage

### Automatic Tracking

The bundle automatically tracks all emails and notifications sent through Symfony's Mailer and Notifier components:

```php
// Emails are automatically tracked
$email = (new Email())
    ->from('hello@example.com')
    ->to('you@example.com')
    ->subject('Time for Symfony Mailer!')
    ->html('<p>See Twig integration for better HTML integration!</p>');

$mailer->send($email);
```

### Manual Tracking

```php
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;

class MyService
{
    public function __construct(
        private MessageTracker $messageTracker
    ) {}

    public function trackCustomMessage(): void
    {
        $message = $this->messageTracker->trackEmail(
            $email,
            'sendgrid',
            $notification,
            ['custom' => 'metadata']
        );
    }
}
```

### Using Templates

```php
use Nkamuo\NotificationTrackerBundle\Entity\MessageTemplate;
use Nkamuo\NotificationTrackerBundle\Service\TemplateRenderer;

$template = $templateRepository->findOneBy(['name' => 'welcome-email']);
$content = $templateRenderer->render($template, [
    'user' => $user,
    'activation_link' => $link,
]);
```

### Webhook Setup

Configure your webhook endpoints with your providers:

- SendGrid: `https://your-domain.com/webhooks/notification-tracker/sendgrid`
- Twilio: `https://your-domain.com/webhooks/notification-tracker/twilio`
- Mailgun: `https://your-domain.com/webhooks/notification-tracker/mailgun`
- Mailchimp: `https://your-domain.com/webhooks/notification-tracker/mailchimp`

### API Access

Browse and manage notifications via API Platform:

```
GET /api/notification-tracker/messages
GET /api/notification-tracker/messages/{ulid}
POST /api/notification-tracker/messages/{ulid}/retry
POST /api/notification-tracker/messages/{ulid}/cancel
GET /api/notification-tracker/notifications
GET /api/notification-tracker/analytics
```

### Console Commands

```bash
# Process failed messages
php bin/console notification-tracker:process-failed

# View analytics
php bin/console notification-tracker:analytics

# Clean up old messages
php bin/console notification-tracker:cleanup --days=90

# Export messages
php bin/console notification-tracker:export --format=csv --from=2024-01-01
```

## Advanced Features

### Custom Webhook Providers

```php
use Nkamuo\NotificationTrackerBundle\Webhook\Provider\AbstractWebhookProvider;

class CustomWebhookProvider extends AbstractWebhookProvider
{
    public function supports(string $provider): bool
    {
        return $provider === 'custom';
    }

    public function verifySignature(array $payload, array $headers): bool
    {
        // Implement signature verification
    }

    public function parseWebhook(array $payload): array
    {
        // Parse webhook payload
    }
}
```

### Event Listeners

```php
use Nkamuo\NotificationTrackerBundle\Event\MessageTrackedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NotificationListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            MessageTrackedEvent::class => 'onMessageTracked',
        ];
    }

    public function onMessageTracked(MessageTrackedEvent $event): void
    {
        $message = $event->getMessage();
        // Custom logic
    }
}
```

## Testing

```bash
composer test
composer cs-fix
composer phpstan
```

## License

MIT

## Support

For issues and feature requests, please use the [GitHub issue tracker](https://github.com/nkamuo/notification-tracker-bundle/issues).