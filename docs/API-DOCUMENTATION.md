# Notification Tracker Bundle - Complete API Documentation

## Table of Contents
1. [Overview](#overview)
2. [Installation & Setup](#installation--setup)
3. [API Endpoints](#api-endpoints)
4. [Entity Schemas](#entity-schemas)
5. [Webhook Integration](#webhook-integration)
6. [UI Component Specifications](#ui-component-specifications)
7. [Code Examples](#code-examples)

## Overview

The Notification Tracker Bundle provides enterprise-grade multi-channel notification management with comprehensive tracking, analytics, and webhook integration for Symfony applications.

### Key Features
- ✅ **Multi-Channel Support**: Email, SMS, Push, Slack, Telegram
- ✅ **Real-time Tracking**: Delivery, opens, clicks, bounces
- ✅ **Webhook Integration**: Automatic event processing from providers
- ✅ **Rich Analytics**: Engagement statistics and performance metrics
- ✅ **API-First Design**: RESTful API with pagination and filtering
- ✅ **Enterprise Ready**: High-performance, scalable architecture

### Supported Channels
| Channel | Provider Support | Webhook Support | Tracking Features |
|---------|-----------------|-----------------|-------------------|
| **Email** | SendGrid, Mailgun, SMTP | ✅ | Opens, Clicks, Bounces, Spam |
| **SMS** | Twilio, Nexmo | ✅ | Delivery, Failure, Replies |
| **Push** | Firebase, OneSignal | ✅ | Delivery, Opens, Actions |
| **Slack** | Webhook, Bot API | ✅ | Delivery, Reactions |
| **Telegram** | Bot API | ✅ | Delivery, Reads, Actions |

## Installation & Setup

### 1. Install via Composer
```bash
composer require nkamuo/notification-tracker-bundle
```

### 2. Enable the Bundle
```php
// config/bundles.php
return [
    // ... other bundles
    Nkamuo\NotificationTrackerBundle\NotificationTrackerBundle::class => ['all' => true],
];
```

### 3. Configure the Bundle
```yaml
# config/packages/notification_tracker.yaml
notification_tracker:
    # Database Configuration
    tracking:
        store_content: true
        cleanup_after_days: 365
        
    # Storage Configuration  
    storage:
        attachment_directory: '%kernel.project_dir%/var/attachments'
        max_attachment_size: 10485760 # 10MB
        allowed_mime_types:
            - 'image/jpeg'
            - 'image/png'
            - 'application/pdf'
            
    # Webhook Configuration
    webhooks:
        async_processing: true
        verify_signatures: true
        providers:
            sendgrid:
                secret: '%env(SENDGRID_WEBHOOK_SECRET)%'
            twilio:
                secret: '%env(TWILIO_WEBHOOK_SECRET)%'
            mailgun:
                secret: '%env(MAILGUN_WEBHOOK_SECRET)%'
```

### 4. Environment Variables
```bash
# .env
SENDGRID_WEBHOOK_SECRET=your_sendgrid_webhook_secret
TWILIO_WEBHOOK_SECRET=your_twilio_webhook_secret
MAILGUN_WEBHOOK_SECRET=your_mailgun_webhook_secret
```

### 5. Run Migrations
```bash
php bin/console doctrine:migrations:migrate
```

## API Endpoints

### Base URL
All endpoints are prefixed with `/notification-tracker`

### Authentication
Configure authentication according to your application's security setup. The bundle supports:
- API Keys
- JWT Tokens  
- Session-based authentication
- OAuth2

---

## 📋 Notifications API

### List Notifications
```http
GET /notification-tracker/notifications
```

**Query Parameters:**
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `page` | integer | Page number (default: 1) | `?page=2` |
| `itemsPerPage` | integer | Items per page (max: 100, default: 20) | `?itemsPerPage=50` |
| `type` | string | Filter by notification type | `?type=welcome` |
| `importance` | string | Filter by importance (low, normal, high, urgent) | `?importance=high` |
| `subject` | string | Search in subject (partial match) | `?subject=welcome` |
| `order[createdAt]` | string | Sort by creation date (asc/desc) | `?order[createdAt]=desc` |
| `createdAt[after]` | datetime | Filter created after date | `?createdAt[after]=2025-09-01` |
| `createdAt[before]` | datetime | Filter created before date | `?createdAt[before]=2025-09-30` |

**Response:**
```json
{
  "@context": "/notification-tracker/contexts/Notification",
  "@id": "/notification-tracker/notifications",
  "@type": "hydra:Collection",
  "hydra:member": [
    {
      "@id": "/notification-tracker/notifications/01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "@type": "Notification",
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "type": "welcome",
      "importance": "normal",
      "subject": "Welcome to Our Platform!",
      "channels": ["email", "sms"],
      "userId": "01ARZ3NDEKTSV4RRFFQ69G5FB0",
      "createdAt": "2025-09-19T10:30:00+00:00",
      "totalMessages": 2,
      "messageStats": {
        "total": 2,
        "sent": 1,
        "delivered": 1,
        "failed": 0,
        "pending": 0,
        "queued": 0,
        "cancelled": 0
      },
      "engagementStats": {
        "totalRecipients": 2,
        "uniqueOpens": 1,
        "uniqueClicks": 0,
        "openRate": 50.0,
        "clickRate": 0.0,
        "bounceRate": 0.0
      }
    }
  ],
  "hydra:totalItems": 150,
  "hydra:view": {
    "@id": "/notification-tracker/notifications?page=1",
    "@type": "hydra:PartialCollectionView",
    "hydra:first": "/notification-tracker/notifications?page=1",
    "hydra:last": "/notification-tracker/notifications?page=8",
    "hydra:next": "/notification-tracker/notifications?page=2"
  }
}
```

### Get Single Notification
```http
GET /notification-tracker/notifications/{id}
```

**Response:**
```json
{
  "@context": "/notification-tracker/contexts/Notification",
  "@id": "/notification-tracker/notifications/01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "@type": "Notification",
  "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "type": "welcome",
  "importance": "normal",
  "subject": "Welcome to Our Platform!",
  "channels": ["email", "sms"],
  "context": {
    "campaign_id": "summer_2025",
    "user_segment": "new_users"
  },
  "userId": "01ARZ3NDEKTSV4RRFFQ69G5FB0",
  "createdAt": "2025-09-19T10:30:00+00:00",
  "messages": [
    {
      "@id": "/notification-tracker/messages/01ARZ3NDEKTSV4RRFFQ69G5FB1",
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FB1",
      "type": "email",
      "status": "delivered",
      "subject": "Welcome to Our Platform!",
      "transportName": "sendgrid",
      "messageId": "provider_message_id_123",
      "createdAt": "2025-09-19T10:30:00+00:00",
      "sentAt": "2025-09-19T10:30:15+00:00",
      "recipients": [
        {
          "id": "01ARZ3NDEKTSV4RRFFQ69G5FB2",
          "type": "to",
          "address": "user@example.com",
          "name": "John Doe",
          "status": "delivered",
          "deliveredAt": "2025-09-19T10:30:30+00:00",
          "openedAt": "2025-09-19T10:45:00+00:00",
          "openCount": 2,
          "clickCount": 0
        }
      ]
    }
  ],
  "totalMessages": 2,
  "messageStats": {
    "total": 2,
    "sent": 2,
    "delivered": 2,
    "failed": 0,
    "pending": 0,
    "queued": 0,
    "cancelled": 0
  },
  "engagementStats": {
    "totalRecipients": 2,
    "uniqueOpens": 1,
    "uniqueClicks": 0,
    "openRate": 50.0,
    "clickRate": 0.0,
    "bounceRate": 0.0
  },
  "channelBreakdown": {
    "email": {
      "total": 1,
      "sent": 1,
      "delivered": 1,
      "openRate": 100.0
    },
    "sms": {
      "total": 1,
      "sent": 1,
      "delivered": 1,
      "replyRate": 0.0
    }
  }
}
```

### Create Notification
```http
POST /notification-tracker/notifications
Content-Type: application/json
```

**Request Body Schema:**
```typescript
interface CreateNotificationRequest {
  type: string;                    // Required: notification type
  importance?: 'low' | 'normal' | 'high' | 'urgent'; // Default: 'normal'
  subject?: string;                // Optional: notification subject
  channels: ('email' | 'sms' | 'push' | 'slack' | 'telegram')[]; // Required
  content?: string;                // Optional: main content
  recipients: Recipient[];         // Required: array of recipients
  channelSettings?: ChannelSettings; // Optional: per-channel configuration
  context?: Record<string, any>;   // Optional: custom metadata
  userId?: string;                 // Optional: associated user ID (ULID)
}

interface Recipient {
  // For Email
  email?: string;
  // For SMS
  phone?: string;
  // For Push
  device_token?: string;
  // For Slack
  channel?: string;
  // For Telegram
  chat_id?: string;
  // Common
  name?: string;
  user_id?: string; // ULID
}

interface ChannelSettings {
  email?: {
    transport?: string;
    from_email?: string;
    from_name?: string;
    subject?: string;
    template_id?: string;
  };
  sms?: {
    transport?: string;
    from_number?: string;
  };
  push?: {
    transport?: string;
    title?: string;
    icon?: string;
    click_action?: string;
  };
  slack?: {
    transport?: string;
    username?: string;
    icon_emoji?: string;
  };
  telegram?: {
    transport?: string;
    parse_mode?: 'HTML' | 'Markdown';
    disable_notification?: boolean;
  };
}
```

**Example Request:**
```json
{
  "type": "welcome",
  "importance": "normal",
  "subject": "Welcome to Our Platform!",
  "channels": ["email", "sms"],
  "content": "Welcome! Thank you for joining our platform. We're excited to have you on board.",
  "recipients": [
    {
      "email": "newuser@example.com",
      "phone": "+1234567890",
      "name": "New User"
    }
  ],
  "channelSettings": {
    "email": {
      "transport": "sendgrid",
      "from_email": "welcome@yourapp.com",
      "from_name": "Your App Team",
      "subject": "🎉 Welcome to Your App!"
    },
    "sms": {
      "transport": "twilio",
      "from_number": "+1987654321"
    }
  },
  "context": {
    "campaign_id": "welcome_series_2025",
    "user_segment": "free_trial",
    "source": "website_signup"
  },
  "userId": "01ARZ3NDEKTSV4RRFFQ69G5FB0"
}
```

**Response:** Returns the created notification with full details (same as GET single notification)

---

## 📧 Messages API

### List Messages
```http
GET /notification-tracker/messages
```

**Query Parameters:**
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `page` | integer | Page number | `?page=2` |
| `itemsPerPage` | integer | Items per page (max: 100, default: 25) | `?itemsPerPage=50` |
| `status` | string | Filter by status | `?status=delivered` |
| `type` | string | Filter by message type | `?type=email` |
| `transportName` | string | Filter by transport | `?transportName=sendgrid` |
| `subject` | string | Search in subject | `?subject=welcome` |
| `notification.type` | string | Filter by notification type | `?notification.type=welcome` |
| `order[createdAt]` | string | Sort by date | `?order[createdAt]=desc` |
| `createdAt[after]` | datetime | Created after date | `?createdAt[after]=2025-09-01` |

**Message Status Values:**
- `pending` - Message created, not yet queued
- `queued` - Message queued for sending
- `sending` - Message being sent
- `sent` - Message sent to provider
- `delivered` - Message delivered to recipient
- `failed` - Message failed to send
- `bounced` - Message bounced
- `cancelled` - Message cancelled
- `retrying` - Message being retried

### Get Single Message
```http
GET /notification-tracker/messages/{id}
```

### Retry Failed Message
```http
POST /notification-tracker/messages/{id}/retry
```

### Cancel Message
```http
POST /notification-tracker/messages/{id}/cancel
```

### Delete Message
```http
DELETE /notification-tracker/messages/{id}
```

---

## 📊 Analytics & Events API

### Message Events
```http
GET /notification-tracker/events
```

**Event Types:**
- `sent` - Message sent to provider
- `delivered` - Message delivered to recipient
- `opened` - Message opened by recipient (email)
- `clicked` - Link clicked in message
- `bounced` - Message bounced
- `failed` - Message failed
- `replied` - Recipient replied (SMS/chat)
- `unsubscribed` - Recipient unsubscribed

### Recipients
```http
GET /notification-tracker/recipients
```

### Message Templates
```http
GET /notification-tracker/templates
```

---

## 🔗 Webhook Endpoints

### Webhook Handler
```http
POST /webhooks/notification-tracker/{provider}
```

**Supported Providers:**
- `sendgrid` - SendGrid webhook events
- `twilio` - Twilio SMS webhooks  
- `mailgun` - Mailgun webhooks
- `firebase` - Firebase push notifications
- `slack` - Slack event subscriptions
- `telegram` - Telegram bot webhooks

## UI Component Specifications

I'll create detailed UI specifications in the next section to help you build the perfect interface.
