# 📧 Notification Tracker Bundle

> **⚠️ EXPERIMENTAL** - This project is in active development and should not be used in production environments. APIs and features may change without notice.

**Automatically track and analyze** all emails and notifications sent through Symfony Mailer and Notifier components.

## 🎯 What This Bundle Does

This bundle **automatically captures and tracks** notifications sent through:
- ✅ **Symfony Mailer** - All emails sent via `$mailer->send()`
- ✅ **Symfony Notifier** - All notifications sent via `$notifier->send()`  
- ✅ **Manual API** - Custom notifications via REST API

**No code changes required!** Just install, configure, and start tracking.

## 🚀 Quick Start

1. **Install**: `composer require nkamuo/notification-tracker-bundle`
2. **Configure**: Add to `bundles.php`
3. **Migrate**: Run `doctrine:migrations:migrate`
4. **Use**: Send emails/notifications as normal - they're automatically tracked!

## 📚 Documentation

**👉 START HERE: [Complete Documentation](docs/MAIN_DOCUMENTATION.md)**

### Essential Links
- 📖 **[Main Guide](docs/MAIN_DOCUMENTATION.md)** - Complete setup and usage
- 🌐 **[API Reference](docs/API_REFERENCE.md)** - REST API documentation  
- 🔍 **[Custom Filters](docs/API_FILTERS.md)** - Advanced API filtering
- ⚠️ **[Experimental Notice](EXPERIMENTAL.md)** - Important warnings

## 🧪 Experimental Status

**This is an experimental project** - use at your own risk:
- ✅ Core tracking functionality works
- ⚠️ APIs may change without notice  
- 🚧 Active development in progress
- 🔬 Suitable for testing and evaluation only

## 🎯 Key Features

- 📧 **Multi-channel Support**: Email, SMS, Slack, Telegram, Push notifications
- 🔄 **Native Symfony Integration**: Works seamlessly with Symfony Mailer and Notifier events
- 📊 **Complete Tracking**: Track sent, delivered, opened, clicked, bounced, and failed messages
- 🔗 **Webhook Support**: Integrate with SendGrid, Twilio, Mailgun, and more
- 🚀 **Async Processing**: Built-in Symfony Messenger support
- 🔍 **API Platform Integration**: Rich REST API for browsing and managing notifications
- 📈 **Real-time Analytics**: Performance metrics and engagement statistics
- ⚙️ **Highly Configurable**: Environment-based configuration with sensible defaults

## 💻 Installation

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

## ⚙️ Basic Configuration

Create `config/packages/notification_tracker.yaml`:

```yaml
notification_tracker:
    enabled: true
    tracking:
        enabled: true
        track_opens: true
        track_clicks: true
```

## 📊 Usage Examples

### Automatic Tracking

The bundle automatically tracks all emails and notifications sent through Symfony's components:

```php
// Emails are automatically tracked
$email = (new Email())
    ->from('hello@example.com')
    ->to('you@example.com')
    ->subject('Time for Symfony Mailer!')
    ->html('<p>See Twig integration for better HTML integration!</p>');

$mailer->send($email);
```

### API Access

Browse and manage notifications via API Platform:

```bash
# List tracked messages
GET /api/notification-tracker/messages

# Get message details  
GET /api/notification-tracker/messages/{id}

# Retry failed message
POST /api/notification-tracker/messages/{id}/retry

# View analytics
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
```

## 🔗 Webhook Setup

Configure your webhook endpoints with your providers:

- **SendGrid**: `https://your-domain.com/webhooks/notification-tracker/sendgrid`
- **Twilio**: `https://your-domain.com/webhooks/notification-tracker/twilio`
- **Mailgun**: `https://your-domain.com/webhooks/notification-tracker/mailgun`

## 🧪 Testing

```bash
composer test
composer cs-fix
composer phpstan
```

## 🤝 Support

For issues and feature requests, please use the [GitHub issue tracker](https://github.com/nkamuo/notification-tracker-bundle/issues).

## 📄 License

MIT

---

**Ready to enhance your notification system with comprehensive tracking and analytics?** 🚀

Check out the [complete documentation](docs/MAIN_DOCUMENTATION.md) to get started!
