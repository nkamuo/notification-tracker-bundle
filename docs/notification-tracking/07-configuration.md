# Configuration Files

## Services Configuration

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\:
        resource: '../src/'
        exclude:
            - '../src/DependencyInjection/'
            - '../src/Entity/'
            - '../src/Kernel.php'

    # Message Tracking Services
    App\Service\Communication\MessageTracker:
        arguments:
            $logger: '@monolog.logger.communication'

    App\Service\Communication\WebhookProcessor:
        arguments:
            $logger: '@monolog.logger.webhook'

    # Webhook Providers
    App\Service\Communication\Provider\:
        resource: '../src/Service/Communication/Provider/'
        tags: ['communication.webhook_provider']

    App\Service\Communication\Provider\SendGridWebhookHandler:
        arguments:
            $webhookSecret: '%env(SENDGRID_WEBHOOK_SECRET)%'
        tags: ['communication.webhook_provider']

    App\Service\Communication\Provider\TwilioWebhookHandler:
        arguments:
            $authToken: '%env(TWILIO_AUTH_TOKEN)%'
        tags: ['communication.webhook_provider']

    App\Service\Communication\Provider\MailgunWebhookHandler:
        arguments:
            $signingKey: '%env(MAILGUN_SIGNING_KEY)%'
        tags: ['communication.webhook_provider']

    # Transport Decorators
    App\Mailer\Transport\TrackingTransportDecorator:
        decorates: mailer.default_transport
        arguments:
            $decoratedTransport: '@.inner'
            $messageTracker: '@App\Service\Communication\MessageTracker'
            $transportName: 'default'

    # Channel Decorators
    App\Notifier\Channel\EmailChannelDecorator:
        decorates: notifier.channel.email
        arguments:
            $decoratedChannel: '@.inner'
            $messageTracker: '@App\Service\Communication\MessageTracker'
            $channelType: 'email'

    App\Notifier\Channel\SmsChannelDecorator:
        decorates: notifier.channel.sms
        arguments:
            $decoratedChannel: '@.inner'
            $messageTracker: '@App\Service\Communication\MessageTracker'
            $channelType: 'sms'

    # Event Listeners
    App\EventListener\WebhookSecurityListener:
        tags:
            - { name: kernel.event_listener, event: kernel.request, priority: 100 }

    App\EventListener\MessageEventListener:
        tags:
            - { name: kernel.event_listener, event: message.event.occurred }

    # Analytics Service
    App\Service\Communication\MessageAnalytics:
        arguments:
            $cache: '@cache.app'
```

## Messenger Configuration

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        default_bus: message.bus
        
        transports:
            async_email:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    exchange:
                        name: messages
                        type: direct
                    queues:
                        email:
                            binding_keys: ['email']
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
                    max_delay: 0

            async_notification:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    exchange:
                        name: messages
                        type: direct
                    queues:
                        notification:
                            binding_keys: ['notification']
                retry_strategy:
                    max_retries: 5
                    delay: 500
                    multiplier: 2
                    max_delay: 10000

            failed:
                dsn: 'doctrine://default?queue_name=failed'

        buses:
            message.bus:
                middleware:
                    - App\Messenger\Middleware\MessageTrackingMiddleware
                    - doctrine_transaction

        routing:
            'Symfony\Component\Mailer\Messenger\SendEmailMessage': async_email
            'Symfony\Component\Notifier\Message\MessageInterface': async_notification
            'App\Message\ProcessWebhookMessage': async_notification
            'App\Message\RetryFailedMessage': async_notification

        failure_transport: failed
```

## Mailer Configuration

```yaml
# config/packages/mailer.yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
        message_bus: message.bus
        
        envelope:
            sender: '%env(MAIL_FROM_ADDRESS)%'
            recipients: []

        headers:
            X-Application: 'Electrodiscount TMS'
            X-Environment: '%kernel.environment%'
```

## Notifier Configuration

```yaml
# config/packages/notifier.yaml
framework:
    notifier:
        channel_policy:
            urgent: ['email', 'sms']
            high: ['email', 'chat/slack']
            medium: ['email']
            low: ['email']

        admin_recipients:
            - { email: admin@electrodiscount.com }

        chatter_transports:
            slack: '%env(SLACK_DSN)%'
            telegram: '%env(TELEGRAM_DSN)%'

        texter_transports:
            twilio: '%env(TWILIO_DSN)%'
```

## Environment Variables

```bash
# .env
# Mailer
MAILER_DSN=tracking://sendgrid+api://KEY@default
MAIL_FROM_ADDRESS=noreply@electrodiscount.com

# Notifier
SLACK_DSN=slack://TOKEN@default?channel=notifications
TELEGRAM_DSN=telegram://TOKEN@default?channel=@notifications
TWILIO_DSN=twilio://SID:TOKEN@default?from=+1234567890

# Webhook Secrets
SENDGRID_WEBHOOK_SECRET=your_sendgrid_webhook_secret
TWILIO_AUTH_TOKEN=your_twilio_auth_token
MAILGUN_SIGNING_KEY=your_mailgun_signing_key

# Messenger
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2f/messages
```

## Routing Configuration

```yaml
# config/routes/webhooks.yaml
webhooks:
    resource: '../src/Controller/Webhook/'
    type: annotation
    prefix: /webhook
    requirements:
        _method: POST
```