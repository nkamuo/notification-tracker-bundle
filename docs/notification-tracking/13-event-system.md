# Event System and Message Enrichment

The Notification Tracker Bundle provides a comprehensive event system that allows applications to hook into various stages of notification and message processing for custom enrichment, validation, and business logic.

## Overview

The event system is built around Symfony's EventDispatcher and provides the following capabilities:

- **Automatic Label Assignment**: Add labels to notifications and messages based on content, recipients, or metadata
- **Content Enrichment**: Modify notification/message content before processing
- **Validation and Business Rules**: Implement custom validation logic that can prevent processing
- **Audit and Logging**: Track processing stages with custom metadata
- **Integration Hooks**: Connect with external systems during message lifecycle

## Available Events

### Notification Events

#### `NotificationCreatedEvent`
- **When**: Dispatched when a notification is created (before saving)
- **Purpose**: Enrich notification data, add labels, set metadata
- **Can Stop Processing**: Yes (via `stopProcessing()`)

#### `NotificationPreSendEvent`
- **When**: Dispatched before sending a notification
- **Purpose**: Final validation, rate limiting, maintenance mode checks
- **Can Cancel Sending**: Yes (via `cancelSending()`)

#### `NotificationPostSendEvent`
- **When**: Dispatched after a notification send attempt
- **Purpose**: Logging, cleanup, follow-up actions
- **Provides**: Success/failure status and error message

### Message Events

#### `MessageCreatedEvent`
- **When**: Dispatched when a message is created
- **Purpose**: Auto-labeling, content enrichment
- **Can Stop Processing**: Yes (via `stopProcessing()`)

#### `MessagePreProcessEvent`
- **When**: Dispatched before processing a message
- **Purpose**: Validation, spam filtering, domain blocking
- **Can Cancel Processing**: Yes (via `cancelProcessing()`)

#### `MessagePostProcessEvent`
- **When**: Dispatched after a message processing attempt
- **Purpose**: Logging, analytics, follow-up actions
- **Provides**: Success/failure status and error message

#### `InboundMessageEvent`
- **When**: Dispatched when processing inbound messages (webhooks)
- **Purpose**: Custom parsing, provider-specific enrichment
- **Provides**: Raw webhook data and provider information

## Event Subscriber Examples

### Auto-Labeling Subscriber

```php
class AutoLabelEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            NotificationCreatedEvent::NAME => 'onNotificationCreated',
            MessageCreatedEvent::NAME => 'onMessageCreated',
            InboundMessageEvent::NAME => 'onInboundMessage',
        ];
    }

    public function onNotificationCreated(NotificationCreatedEvent $event): void
    {
        $notification = $event->getNotification();
        
        // Auto-label based on type
        if ($notification->getType() === 'marketing') {
            $this->enrichmentService->addLabel($notification, 'marketing');
        }
        
        // Auto-label based on subject content
        if (str_contains(strtolower($notification->getSubject()), 'urgent')) {
            $this->enrichmentService->addLabel($notification, 'urgent');
        }
    }
}
```

### Validation Subscriber

```php
class ValidationEventSubscriber implements EventSubscriberInterface
{
    public function onNotificationPreSend(NotificationPreSendEvent $event): void
    {
        $notification = $event->getNotification();
        
        // Block during maintenance windows
        if ($this->isMaintenanceWindow()) {
            $event->cancelSending();
            return;
        }
        
        // Rate limiting for marketing
        if ($this->hasLabel($notification, 'marketing') && $this->isRateLimited()) {
            $event->cancelSending();
            return;
        }
    }
}
```

## Using the EventEnrichmentService

The `EventEnrichmentService` provides utility methods for common enrichment tasks:

### Adding Labels

```php
// Add a simple label
$enrichmentService->addLabel($notification, 'important');

// Add a label with description
$enrichmentService->addLabel($message, 'support-ticket', 'Customer support inquiry');
```

### Adding Metadata

```php
// Add custom metadata
$enrichmentService->addMetadata($notification, 'source_campaign', 'summer-2024');
$enrichmentService->addMetadata($message, 'processing_time', microtime(true));
```

### Triggering Events Manually

```php
// Enrich notification at creation
$shouldContinue = $enrichmentService->enrichNotificationOnCreation($notification, $context);

// Check pre-send conditions
$shouldSend = $enrichmentService->enrichNotificationPreSend($notification, $context);

// Process post-send events
$enrichmentService->processNotificationPostSend($notification, $success, $errorMessage, $context);
```

## API Filtering with Labels

With the enhanced event system, you can now filter notifications and messages by labels through the API:

### Filter by Label Name

```http
GET /api/notifications?labels.name=marketing
GET /api/messages?labels.name=urgent
```

### Filter by Multiple Criteria

```http
GET /api/notifications?status=sent&labels.name=important&direction=outbound
GET /api/messages?labels.name=support-ticket&status=delivered
```

### Advanced Filtering

```http
# Get all marketing notifications sent in the last week
GET /api/notifications?labels.name=marketing&status=sent&sentAt[after]=2024-01-01

# Get all urgent inbound messages
GET /api/messages?labels.name=urgent&direction=inbound
```

## Event Context

Events provide context arrays that contain information about the processing stage:

```php
$context = [
    'source' => 'api_controller',
    'provider' => 'sendgrid',
    'request_data' => '{"subject": "Test"}',
    'processing_time' => 0.123
];
```

Context can be read and modified by event listeners to pass information between stages.

## Custom Event Listeners

You can create custom event listeners for specific business needs:

### Integration with External Systems

```php
class CrmIntegrationSubscriber implements EventSubscriberInterface
{
    public function onMessageCreated(MessageCreatedEvent $event): void
    {
        $message = $event->getMessage();
        
        // Sync with CRM system
        $crmData = $this->crmService->lookupContact($message->getFromAddress());
        if ($crmData) {
            $this->enrichmentService->addMetadata($message, 'crm_contact_id', $crmData['id']);
            $this->enrichmentService->addLabel($message, 'crm-synced');
        }
    }
}
```

### Custom Business Rules

```php
class BusinessRulesSubscriber implements EventSubscriberInterface
{
    public function onNotificationPreSend(NotificationPreSendEvent $event): void
    {
        $notification = $event->getNotification();
        
        // Check user preferences
        $recipientData = $this->getUserPreferences($notification->getRecipients());
        if (!$recipientData['accepts_marketing']) {
            $event->cancelSending();
        }
        
        // Add compliance metadata
        $this->enrichmentService->addMetadata($notification, 'gdpr_consent', $recipientData['gdpr_consent']);
    }
}
```

## Best Practices

1. **Keep Event Listeners Lightweight**: Avoid heavy operations in event listeners
2. **Use Context for Communication**: Pass data between stages using the context array
3. **Log Important Decisions**: Use the logger to track why processing was stopped or modified
4. **Handle Exceptions**: Wrap potentially failing operations in try-catch blocks
5. **Test Event Logic**: Write unit tests for your custom event subscribers
6. **Use Specific Events**: Subscribe only to the events you need to avoid unnecessary processing

## Configuration

The event system is automatically configured when you install the bundle. Event subscribers are auto-registered with the `kernel.event_subscriber` tag.

To disable specific subscribers, you can remove them from the service configuration:

```yaml
# services.yaml
services:
    # Disable auto-labeling
    Nkamuo\NotificationTrackerBundle\EventSubscriber\AutoLabelEventSubscriber: ~
    
    # Or configure with custom parameters
    App\EventSubscriber\CustomValidationSubscriber:
        arguments:
            $maxRetries: 3
            $timeoutSeconds: 30
        tags:
            - { name: kernel.event_subscriber }
```
