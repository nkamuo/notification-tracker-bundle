# API Route Consistency Documentation

## Overview

All API routes in the Notification Tracker Bundle now use a consistent prefix structure to ensure clean and organized endpoint organization.

## Base Configuration

- **Base Prefix**: `/notification-tracker`
- **Configuration Class**: `Nkamuo\NotificationTrackerBundle\Config\ApiRoutes`

## Endpoint Structure

### 📬 Notifications
**Prefix**: `/notification-tracker/notifications`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/notification-tracker/notifications` | List all notifications (paginated) |
| GET | `/notification-tracker/notifications/{id}` | Get specific notification details |

**Features**:
- Pagination: 20 items per page (max 100)
- Filtering: type, importance, subject
- Ordering: createdAt, type, importance, subject
- Rich statistics included in responses

### 📧 Messages  
**Prefix**: `/notification-tracker/messages`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/notification-tracker/messages` | List all messages (paginated) |
| GET | `/notification-tracker/messages/{id}` | Get specific message details |
| POST | `/notification-tracker/messages/{id}/retry` | Retry failed message |
| POST | `/notification-tracker/messages/{id}/cancel` | Cancel pending message |
| DELETE | `/notification-tracker/messages/{id}` | Delete message |

**Features**:
- Pagination: 25 items per page (max 100)
- Filtering: status, type, transportName, subject, notification properties
- Ordering: createdAt, sentAt, status
- Engagement statistics included

### 📝 Templates
**Prefix**: `/notification-tracker/templates`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/notification-tracker/templates` | List all templates |
| GET | `/notification-tracker/templates/{id}` | Get specific template |
| POST | `/notification-tracker/templates` | Create new template |
| PUT | `/notification-tracker/templates/{id}` | Update template |
| DELETE | `/notification-tracker/templates/{id}` | Delete template |

### 📊 Events
**Prefix**: `/notification-tracker/messages/{id}/events`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/notification-tracker/messages/{id}/events` | Get events for specific message |

## Message Subtypes

All message subtypes (EmailMessage, SmsMessage, SlackMessage, TelegramMessage, PushMessage) inherit their routes from the parent Message class, ensuring consistent endpoints:

- **EmailMessage**: Uses `/notification-tracker/messages` endpoints
- **SmsMessage**: Uses `/notification-tracker/messages` endpoints  
- **SlackMessage**: Uses `/notification-tracker/messages` endpoints
- **TelegramMessage**: Uses `/notification-tracker/messages` endpoints
- **PushMessage**: Uses `/notification-tracker/messages` endpoints

## Reserved Prefixes

The following prefixes are reserved for future use:

- `/notification-tracker/recipients` - For recipient management endpoints
- `/notification-tracker/webhooks` - For webhook management endpoints  
- `/notification-tracker/statistics` - For analytics endpoints
- `/notification-tracker/attachments` - For attachment management endpoints

## Benefits

✅ **Consistent Structure**: All endpoints follow the same naming convention
✅ **Logical Organization**: Related resources are grouped under logical prefixes
✅ **Future-Proof**: Reserved prefixes ensure clean expansion
✅ **Developer Experience**: Predictable endpoint patterns
✅ **Documentation**: Clear API structure for consumers

## Usage in Code

### Using the ApiRoutes Configuration Class

```php
use Nkamuo\NotificationTrackerBundle\Config\ApiRoutes;

// Get notification endpoints
$notificationsList = ApiRoutes::getNotification(); // '/notification-tracker/notifications'
$notificationDetail = ApiRoutes::getNotification('/{id}'); // '/notification-tracker/notifications/{id}'

// Get message endpoints  
$messagesList = ApiRoutes::getMessage(); // '/notification-tracker/messages'
$messageDetail = ApiRoutes::getMessage('/{id}'); // '/notification-tracker/messages/{id}'

// All available prefixes
$allPrefixes = ApiRoutes::ALL_PREFIXES;
```

### In Entity Annotations

```php
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: ApiRoutes::getNotification(),
            // ... other configuration
        ),
        new Get(
            uriTemplate: ApiRoutes::getNotification('/{id}'),
            // ... other configuration
        ),
    ]
)]
```

## Migration Notes

If you were using custom endpoints before this update, all routes now follow the consistent `/notification-tracker/` prefix structure. Update your API consumers accordingly.

## Validation

All route prefixes are validated through the `ApiRoutes::ALL_PREFIXES` constant, ensuring consistency across the entire bundle.
