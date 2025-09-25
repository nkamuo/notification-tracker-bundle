# 🌐 API Reference

> **⚠️ EXPERIMENTAL API** - These endpoints are in development and may change without notice.

## Base URL

All API endpoints are prefixed with `/api/notification-tracker`

## Authentication

Currently, API endpoints may require authentication depending on your Symfony security configuration.

---

## 📧 Messages API

### List Messages

Get a paginated list of tracked messages (emails, SMS, push notifications, etc.)

```http
GET /api/notification-tracker/messages
```

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `status` | string | Filter by message status | `?status=sent` |
| `type` | string | Filter by message type | `?type=email` |
| `createdAt[after]` | date | Messages after date | `?createdAt[after]=2024-01-01` |
| `createdAt[before]` | date | Messages before date | `?createdAt[before]=2024-12-31` |
| `subject` | string | Search by subject (partial) | `?subject=welcome` |
| `transportName` | string | Filter by transport | `?transportName=mailgun` |

#### Custom Filters (Experimental)

| Parameter | Description | Example |
|-----------|-------------|---------|
| `status[ne]` | Exclude specific status | `?status[ne]=pending` |
| `status[notin]` | Exclude multiple statuses | `?status[notin]=pending,failed` |
| `type[ne]` | Exclude message type | `?type[ne]=email` |
| `notification.direction[ne]` | Exclude by direction | `?notification.direction[ne]=draft` |

#### Response Example

```json
[
  {
    "id": "01HXA5Z9K2N3M4P5Q6R7S8T9V0",
    "subject": "Welcome to our platform!",
    "status": "sent",
    "direction": "outbound", 
    "messageType": "email",
    "transportName": "smtp",
    "createdAt": "2024-01-15T10:30:00+00:00",
    "sentAt": "2024-01-15T10:30:05+00:00",
    "primaryRecipient": "user@example.com",
    "recipientCount": 1,
    "retryCount": 0,
    "hasScheduleOverride": false,
    "engagementStats": {
      "total_recipients": 1,
      "opened": 0,
      "clicked": 0,
      "bounced": 0,
      "total_opens": 0,
      "total_clicks": 0
    },
    "notification": "/api/notification-tracker/notifications/01HXA5Z9K2N3M4P5Q6R7S8T9V1",
    "labels": [],
    "latestEvent": {
      "type": "sent",
      "occurred_at": "2024-01-15T10:30:05+00:00",
      "metadata": {
        "provider_message_id": "abc123@mailgun.org"
      }
    }
  }
]
```

### Get Single Message

Get detailed information about a specific message.

```http
GET /api/notification-tracker/messages/{id}
```

#### Response Example

```json
{
  "id": "01HXA5Z9K2N3M4P5Q6R7S8T9V0",
  "subject": "Welcome to our platform!",
  "status": "sent",
  "direction": "outbound",
  "messageType": "email",
  "transportName": "smtp",
  "createdAt": "2024-01-15T10:30:00+00:00",
  "sentAt": "2024-01-15T10:30:05+00:00",
  "recipients": [
    {
      "type": "email",
      "value": "user@example.com",
      "name": "John Doe"
    }
  ],
  "content": {
    "textContent": "Welcome to our platform...",
    "htmlContent": "<h1>Welcome...</h1>",
    "metadata": {}
  },
  "events": [
    {
      "type": "queued",
      "occurred_at": "2024-01-15T10:30:00+00:00"
    },
    {
      "type": "sent", 
      "occurred_at": "2024-01-15T10:30:05+00:00",
      "metadata": {
        "provider_message_id": "abc123@mailgun.org"
      }
    }
  ]
}
```

### Retry Failed Message

Retry sending a failed message.

```http
POST /api/notification-tracker/messages/{id}/retry
```

#### Response

```json
{
  "success": true,
  "message": "Message queued for retry",
  "retryCount": 1
}
```

---

## 📋 Notifications API

### List Notifications

Get a paginated list of notifications (which can generate multiple messages).

```http
GET /api/notification-tracker/notifications
```

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `type` | string | Filter by notification type | `?type=welcome` |
| `status` | string | Filter by notification status | `?status=sent` |
| `direction` | string | Filter by direction | `?direction=outbound` |
| `importance` | string | Filter by importance | `?importance=high` |
| `subject` | string | Search by subject | `?subject=order` |

#### Response Example

```json
[
  {
    "id": "01HXA5Z9K2N3M4P5Q6R7S8T9V1",
    "type": "welcome_email",
    "subject": "Welcome to our platform!",
    "status": "sent",
    "direction": "outbound",
    "importance": "normal",
    "createdAt": "2024-01-15T10:30:00+00:00",
    "sentAt": "2024-01-15T10:30:05+00:00",
    "channels": ["email"],
    "messageCount": 1,
    "labels": []
  }
]
```

### Create Notification

Create a new notification that will generate messages.

```http
POST /api/notification-tracker/notifications
```

#### Request Body

```json
{
  "type": "order_confirmation",
  "subject": "Your Order is Confirmed",
  "importance": "normal",
  "direction": "outbound", 
  "channels": ["email", "sms"],
  "context": {
    "order_id": "12345",
    "customer_name": "John Doe",
    "total": 99.99
  },
  "recipients": [
    {
      "type": "email",
      "value": "customer@example.com", 
      "name": "John Doe"
    },
    {
      "type": "phone",
      "value": "+1234567890"
    }
  ],
  "templateData": {
    "order_id": "12345",
    "items": [
      {"name": "Product A", "price": 49.99},
      {"name": "Product B", "price": 49.99}
    ]
  }
}
```

#### Response

```json
{
  "id": "01HXA5Z9K2N3M4P5Q6R7S8T9V1",
  "type": "order_confirmation", 
  "status": "queued",
  "messages": [
    "/api/notification-tracker/messages/01HXA5Z9K2N3M4P5Q6R7S8T9V2",
    "/api/notification-tracker/messages/01HXA5Z9K2N3M4P5Q6R7S8T9V3"
  ],
  "createdAt": "2024-01-15T10:30:00+00:00"
}
```

---

## 📊 Analytics API

### Dashboard Overview

Get high-level analytics for the dashboard.

```http
GET /api/notification-tracker/analytics/dashboard
```

#### Query Parameters

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `period` | string | Time period (7d, 30d, 90d) | `30d` |

#### Response Example

```json
{
  "period": "30d",
  "summary": {
    "totalMessages": 1250,
    "totalSent": 1180,
    "totalFailed": 70,
    "deliveryRate": 94.4,
    "engagementRate": 12.5
  },
  "channelBreakdown": {
    "email": {
      "total": 800,
      "sent": 760,
      "failed": 40,
      "deliveryRate": 95.0
    },
    "sms": {
      "total": 300,
      "sent": 280,
      "failed": 20, 
      "deliveryRate": 93.3
    },
    "push": {
      "total": 150,
      "sent": 140,
      "failed": 10,
      "deliveryRate": 93.3
    }
  },
  "recentActivity": [
    {
      "date": "2024-01-15",
      "sent": 45,
      "failed": 3
    }
  ]
}
```

### Channel Performance

Get detailed performance metrics by channel.

```http
GET /api/notification-tracker/analytics/channels
```

#### Response Example

```json
{
  "period": "30d",
  "channels": {
    "email": {
      "total": 800,
      "sent": 760,
      "delivered": 720,
      "failed": 40,
      "deliveryRate": 95.0,
      "engagementRate": 15.2,
      "cost": 12.80
    },
    "sms": {
      "total": 300, 
      "sent": 280,
      "delivered": 275,
      "failed": 20,
      "deliveryRate": 93.3,
      "engagementRate": 8.5,
      "cost": 45.00
    }
  },
  "comparison": [],
  "recommendations": [
    "Email delivery rate is excellent at 95%",
    "Consider optimizing SMS content for better engagement"
  ]
}
```

### Failure Analysis

Get information about failed messages and common failure patterns.

```http
GET /api/notification-tracker/analytics/failures
```

#### Response Example

```json
{
  "period": "30d",
  "groupBy": "reason",
  "failures": [
    {
      "reason": "Invalid email address",
      "count": 25,
      "percentage": 35.7
    },
    {
      "reason": "Mailbox full",
      "count": 18,
      "percentage": 25.7
    },
    {
      "reason": "Bounce - spam filter",
      "count": 15,
      "percentage": 21.4
    }
  ],
  "trends": [
    {
      "date": "2024-01-15",
      "failures": 5
    }
  ],
  "recommendations": [
    "Implement email validation before sending",
    "Consider retry logic for temporary failures"
  ]
}
```

---

## 🔧 Queue Status API

Get information about the message processing queue.

```http
GET /api/notification-tracker/messages/queue/status
```

#### Response Example

```json
{
  "queues": {
    "pending": 25,
    "queued": 10, 
    "processing": 3,
    "failed": 5,
    "sent": 1250,
    "lastProcessed": "2024-01-15T10:35:00+00:00",
    "throughput": 125
  },
  "summary": {
    "totalPending": 38,
    "totalProcessing": 3,
    "totalFailed": 5,
    "workersActive": 2,
    "lastProcessed": "2024-01-15T10:35:00+00:00",
    "throughput": 125
  },
  "workers": {
    "active": [
      {
        "id": "worker-1",
        "status": "running",
        "lastSeen": "2024-01-15T10:35:00+00:00",
        "messagesProcessed": 500,
        "uptime": "2h 15m"
      }
    ]
  },
  "health": {
    "status": "healthy",
    "checks": {
      "database": {"status": "ok"},
      "memory": {"status": "ok", "usage": "45%"},
      "queue_depth": {"status": "ok", "depth": 38}
    }
  }
}
```

---

## 📝 Message Status Values

| Status | Description |
|--------|-------------|
| `pending` | Message created, not yet queued |
| `queued` | Message queued for sending |
| `sending` | Currently being sent |
| `sent` | Successfully sent |
| `delivered` | Confirmed delivered (via webhook) |
| `failed` | Send failed |
| `bounced` | Email bounced |
| `cancelled` | Manually cancelled |
| `retrying` | Failed, but retrying |

## 📨 Message Types

| Type | Description |
|------|-------------|
| `email` | Email message |
| `sms` | SMS text message |
| `push` | Push notification |
| `slack` | Slack message |
| `telegram` | Telegram message |

## 🧭 Notification Directions

| Direction | Description |
|-----------|-------------|
| `outbound` | Messages being sent out |
| `inbound` | Messages received (webhooks, replies) |
| `draft` | Draft notifications not yet sent |

---

## ⚠️ Error Responses

All endpoints may return these error responses:

### 400 Bad Request
```json
{
  "code": 400,
  "message": "Invalid request parameters",
  "errors": {
    "status": "Invalid status value"
  }
}
```

### 404 Not Found
```json
{
  "code": 404,
  "message": "Message not found"
}
```

### 500 Internal Server Error
```json
{
  "code": 500,
  "message": "Internal server error",
  "type": "/errors/500"
}
```

---

## 🔗 Related Documentation

- [Main Documentation](MAIN_DOCUMENTATION.md) - Complete setup guide
- [Custom Filters](API_FILTERS.md) - Advanced filtering options
- [Experimental Notice](../EXPERIMENTAL.md) - Important warnings about experimental status
