# Notification Tracker Bundle

Notification and email tracking bundle for Symfony 7.3+ with webhook support, file attachments, templates, and comprehensive analytics.

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