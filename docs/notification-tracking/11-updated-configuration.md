# Updated Configuration with Native Event Integration

## Services Configuration

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Event Subscribers for native Symfony events
    App\EventSubscriber\MailerEventSubscriber:
        arguments:
            $logger: '@monolog.logger.mailer_tracking'
        tags:
            - { name: kernel.event_subscriber }

    App\EventSubscriber\NotifierEventSubscriber:
        arguments:
            $logger: '@monolog.logger.notifier_tracking'
        tags:
            - { name: kernel.event_subscriber }

    # Message Handlers with priority
    App\MessageHandler\TrackEmailMessageHandler:
        arguments:
            $logger: '@monolog.logger.message_tracking'
        tags:
            - { name: messenger.message_handler, priority: 100 }

    App\MessageHandler\ProcessWebhookMessageHandler:
        arguments:
            $logger: '@monolog.logger.webhook'

    # Messenger Middleware
    App\Messenger\Middleware\MessageTrackingMiddleware:
        arguments:
            $logger: '@monolog.logger.messenger'

    # Notification Tracker Service
    App\Service\Communication\NotificationTracker:
        arguments:
            $logger: '@monolog.logger.notification'

    # Remove transport decorators since we're using native events
    # Transport decorators are now optional - only use if you need
    # additional transport-specific tracking

    # Webhook Processor with async support
    App\Service\Communication\WebhookProcessor:
        arguments:
            $logger: '@monolog.logger.webhook'
            $messageBus: '@message.bus'

    # Console Commands
    App\Command\Communication\ProcessFailedMessagesCommand:
        tags:
            - { name: console.command }

    App\Command\Communication\MessageAnalyticsCommand:
        tags:
            - { name: console.command }
```

## Updated Messenger Configuration

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

            async_webhook:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    exchange:
                        name: webhooks
                        type: direct
                    queues:
                        webhook:
                            binding_keys: ['webhook']
                retry_strategy:
                    max_retries: 5
                    delay: 2000
                    multiplier: 3
                    max_delay: 60000

            failed:
                dsn: 'doctrine://default?queue_name=failed'

        buses:
            message.bus:
                middleware:
                    - App\Messenger\Middleware\MessageTrackingMiddleware
                    - doctrine_transaction
                    - validation

        routing:
            'Symfony\Component\Mailer\Messenger\SendEmailMessage': async_email
            'Symfony\Component\Notifier\Message\MessageInterface': async_notification
            'App\Message\ProcessWebhookMessage': async_webhook
            'App\Message\RetryFailedMessage': async_notification
            'App\Message\CleanupOldMessages': async_notification

        failure_transport: failed

        # New: configure event dispatcher for messenger
        after_dispatch:
            enabled: true
            event_dispatcher: event_dispatcher
```

## Monolog Configuration

```yaml
# config/packages/monolog.yaml
monolog:
    channels:
        - mailer_tracking
        - notifier_tracking
        - message_tracking
        - webhook
        - messenger
        - notification
        - communication

    handlers:
        mailer_tracking:
            type: stream
            path: '%kernel.logs_dir%/mailer_tracking.log'
            level: info
            channels: ['mailer_tracking']

        notifier_tracking:
            type: stream
            path: '%kernel.logs_dir%/notifier_tracking.log'
            level: info
            channels: ['notifier_tracking']

        message_tracking:
            type: stream
            path: '%kernel.logs_dir%/message_tracking.log'
            level: info
            channels: ['message_tracking']

        webhook:
            type: rotating_file
            path: '%kernel.logs_dir%/webhook.log'
            level: info
            channels: ['webhook']
            max_files: 30

        messenger:
            type: stream
            path: '%kernel.logs_dir%/messenger.log'
            level: info
            channels: ['messenger']

        communication:
            type: group
            members: ['mailer_tracking', 'notifier_tracking', 'message_tracking', 'webhook']
            channels: ['communication']
```

## Event Dispatcher Configuration

```yaml
# config/packages/event_dispatcher.yaml
framework:
    event_dispatcher:
        # Enable event subscriber autoconfiguration
        autoconfigure: true
```