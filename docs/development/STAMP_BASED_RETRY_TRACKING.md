# Notification Tracking Stamp Implementation

## Overview

This implementation solves the problem of duplicate message tracking when using retry mechanisms in Symfony Messenger. Previously, each retry attempt was being tracked as a separate message instead of events under the same message.

## Problem

With RoundRobinTransport and other retry mechanisms, each retry creates a new Email object, causing the tracking system to treat retries as completely new messages rather than retry events of the existing message. This leads to:

- Duplicate notifications in the database
- Inaccurate tracking data
- Bloated message counts
- Poor analytics due to artificial inflation of sent messages

## Solution

The solution implements a **Messenger Stamp-based tracking system** that provides:

1. **Unique Message Identity**: Each message gets a unique ULID that persists across retries
2. **Automatic Stamp Assignment**: Middleware automatically adds stamps to messages
3. **Retry Detection**: The system can differentiate between new messages and retries
4. **Proper Event Tracking**: Retries create events under existing messages instead of new messages

## Components

### 1. NotificationTrackingStamp

```php
// src/Messenger/Stamp/NotificationTrackingStamp.php
readonly class NotificationTrackingStamp implements StampInterface
{
    public function __construct(
        private string $id
    ) {}
    
    public function getId(): string 
    {
        return $this->id;
    }
}
```

**Purpose**: Provides a unique identifier that survives message serialization, transport changes, and retry attempts.

### 2. NotificationTrackingMiddleware

```php
// src/Messenger/Middleware/NotificationTrackingMiddleware.php
class NotificationTrackingMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$message instanceof SendEmailMessage) {
            return $stack->next()->handle($envelope, $stack);
        }

        // Add stamp if not present
        $stamp = $envelope->last(NotificationTrackingStamp::class);
        if (null === $stamp) {
            $trackingId = (string) new Ulid();
            $stamp = new NotificationTrackingStamp($trackingId);
            $envelope = $envelope->with($stamp);
        }

        // Add stamp ID to email headers for tracking
        $email = $message->getMessage();
        if ($email instanceof Email && !$email->getHeaders()->has('X-Stamp-ID')) {
            $email->getHeaders()->addTextHeader('X-Stamp-ID', $stamp->getId());
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
```

**Purpose**: Automatically adds NotificationTrackingStamp to SendEmailMessage if not present and adds the stamp ID to email headers for downstream tracking.

### 3. Enhanced Message Entity

```php
// src/Entity/Message.php - Added fields:
#[ORM\Column(type: 'string', length: 255, nullable: true)]
#[ORM\Index(name: 'IDX_messenger_stamp_id')]
#[Groups(['message:read', 'message:write'])]
private ?string $messengerStampId = null;

#[ORM\Column(type: 'string', length: 255, nullable: true)]
#[ORM\Index(name: 'IDX_content_fingerprint')]
#[Groups(['message:read'])]
private ?string $contentFingerprint = null;
```

**Purpose**: 
- `messengerStampId`: Primary identifier for retry detection
- `contentFingerprint`: SHA256 hash of content for analytics (secondary)

### 4. Enhanced Repository Methods

```php
// src/Repository/MessageRepository.php - New methods:
public function findByStampId(string $stampId): ?Message
public function findByFingerprint(string $fingerprint): array
public function existsByStampId(string $stampId): bool
```

**Purpose**: Efficient querying for existing messages using stamp IDs and content fingerprints.

### 5. Updated Event Subscriber

The `MailerEventSubscriber` now:

1. **Checks for stamp ID** in email headers first (set by middleware)
2. **Creates new messages** with stamp ID and content fingerprint
3. **Detects retries** via `SendMessageToTransportsEvent` using stamp lookup
4. **Adds retry events** instead of creating duplicate messages

## Architecture Benefits

### 1. Stamp-based Identity (Primary)
- **Reliable**: Survives serialization, transport changes, failures
- **Unique**: ULID generation ensures no collisions
- **Persistent**: Same ID used throughout message lifecycle
- **Standard**: Uses Symfony's recommended messenger stamp pattern

### 2. Content Fingerprint (Analytics)
- **Analytical**: Used for detecting content-based duplicates
- **Secondary**: Only used when stamp is unavailable
- **Comprehensive**: Includes subject, recipients, body, attachments

### 3. Retry Detection Flow

```
New Message:
1. Middleware adds NotificationTrackingStamp + X-Stamp-ID header
2. MailerEventSubscriber extracts stamp ID
3. Creates new Message with stamp ID and content fingerprint
4. Message sent successfully or fails

Retry Attempt:
1. Same stamp ID preserved in envelope
2. SendMessageToTransportsEvent triggered
3. Repository lookup finds existing message by stamp ID
4. Adds retry event to existing message (not new message)
5. Retry continues with same tracking identity
```

## Database Migration

```sql
-- migrations/Version20241222190000.php
ALTER TABLE communication_messages ADD messenger_stamp_id VARCHAR(255) DEFAULT NULL;
ALTER TABLE communication_messages ADD content_fingerprint VARCHAR(255) DEFAULT NULL;
CREATE INDEX IDX_messenger_stamp_id ON communication_messages (messenger_stamp_id);
CREATE INDEX IDX_content_fingerprint ON communication_messages (content_fingerprint);
```

## Configuration

The middleware is automatically registered via service configuration:

```yaml
# src/Resources/config/services.yaml
Nkamuo\NotificationTrackerBundle\Messenger\Middleware\NotificationTrackingMiddleware:
    tags:
        - { name: messenger.middleware }
```

## Testing

The implementation includes comprehensive tests for:
- Automatic stamp addition
- Stamp preservation on retries  
- Header injection
- Retry detection logic
- Repository methods

## Performance Considerations

1. **Indexed Fields**: Both stamp ID and content fingerprint have database indexes
2. **Efficient Queries**: Repository methods use single-field lookups
3. **Minimal Overhead**: Middleware only processes SendEmailMessage
4. **ULID Generation**: Fast, ordered, collision-resistant identifiers

## Backward Compatibility

- Existing messages without stamps continue to work
- X-Tracking-ID header fallback preserved
- Content fingerprint still available for analytics
- No breaking changes to existing APIs

## Summary

This implementation provides robust, reliable retry tracking that:
- **Eliminates duplicate messages** caused by retry mechanisms
- **Provides accurate analytics** by tracking retries as events, not messages
- **Uses Symfony best practices** with messenger stamps
- **Maintains performance** with indexed database fields
- **Preserves compatibility** with existing tracking mechanisms

The stamp-based approach is the correct architectural solution for Symfony Messenger retry scenarios, ensuring message identity persists across all retry attempts and transport failures.
