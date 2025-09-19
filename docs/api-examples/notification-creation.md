# API Documentation: Creating Notifications

## Overview
The Notification Tracker Bundle now supports creating notifications via POST requests with rich configuration for multiple channels and recipients.

## Endpoint
```
POST /notification-tracker/notifications
```

## Features
✅ **Pagination Fixed**: All endpoints now properly support pagination with 20-25 items per page (max 100)  
✅ **Multi-channel Support**: Email, SMS, Push, Slack, Telegram  
✅ **Rich Configuration**: Per-channel settings and transport configuration  
✅ **Automatic Message Creation**: Creates appropriate message entities for each channel  
✅ **Flexible Recipients**: Support for different recipient types per channel  

## Request Body Schema

```json
{
  "type": "string", // Required: notification type (e.g., "welcome", "alert", "marketing")
  "importance": "string", // Optional: "low", "normal", "high", "urgent" (default: "normal")
  "subject": "string", // Optional: notification subject/title
  "channels": ["email", "sms", "push", "slack", "telegram"], // Required: array of channels
  "content": "string", // Optional: main content/message body
  "recipients": [
    // Email recipients
    {
      "email": "user@example.com",
      "name": "John Doe"
    },
    // SMS recipients  
    {
      "phone": "+1234567890",
      "name": "Jane Smith"
    },
    // Push notification recipients
    {
      "device_token": "fcm_device_token_here",
      "name": "Mobile User"
    },
    // Slack recipients
    {
      "channel": "#general",
      "name": "Team Channel"
    },
    // Telegram recipients
    {
      "chat_id": "123456789",
      "name": "Telegram User"
    }
  ],
  "channelSettings": {
    "email": {
      "transport": "sendgrid",
      "from_email": "noreply@yourapp.com",
      "from_name": "Your App",
      "subject": "Custom Email Subject"
    },
    "sms": {
      "transport": "twilio",
      "from_number": "+1234567890"
    },
    "push": {
      "transport": "firebase",
      "title": "Push Notification Title"
    },
    "slack": {
      "transport": "slack_webhook"
    },
    "telegram": {
      "transport": "telegram_bot"
    }
  },
  "context": {
    "campaign_id": "12345",
    "user_segment": "premium",
    "any_custom_data": "value"
  }
}
```

## Example Requests

### 1. Simple Email Notification
```json
{
  "type": "welcome",
  "importance": "normal",
  "subject": "Welcome to Our Platform!",
  "channels": ["email"],
  "content": "<h1>Welcome!</h1><p>Thank you for joining us.</p>",
  "recipients": [
    {
      "email": "newuser@example.com",
      "name": "New User"
    }
  ],
  "channelSettings": {
    "email": {
      "transport": "default",
      "from_email": "welcome@yourapp.com",
      "from_name": "Your App Team"
    }
  }
}
```

### 2. Multi-Channel Emergency Alert
```json
{
  "type": "emergency_alert",
  "importance": "urgent",
  "subject": "System Maintenance Alert",
  "channels": ["email", "sms", "slack"],
  "content": "URGENT: System maintenance will begin in 30 minutes. Please save your work.",
  "recipients": [
    {
      "email": "admin@company.com",
      "name": "System Admin",
      "phone": "+1234567890"
    }
  ],
  "channelSettings": {
    "email": {
      "transport": "sendgrid",
      "from_email": "alerts@company.com",
      "from_name": "System Alerts"
    },
    "sms": {
      "transport": "twilio",
      "from_number": "+1987654321"
    },
    "slack": {
      "transport": "slack_webhook"
    }
  },
  "context": {
    "alert_level": "critical",
    "maintenance_window": "2025-09-19T02:00:00Z"
  }
}
```

### 3. Marketing Campaign (All Channels)
```json
{
  "type": "marketing_campaign",
  "importance": "normal",
  "subject": "🎉 Special Offer Just for You!",
  "channels": ["email", "push", "telegram"],
  "content": "Don't miss our exclusive 50% off sale! Limited time offer.",
  "recipients": [
    {
      "email": "customer@example.com",
      "name": "Valued Customer",
      "device_token": "fcm_token_here",
      "chat_id": "telegram_chat_id"
    }
  ],
  "channelSettings": {
    "email": {
      "transport": "mailgun",
      "from_email": "offers@yourstore.com",
      "from_name": "Your Store",
      "subject": "🎉 Exclusive 50% Off - Limited Time!"
    },
    "push": {
      "transport": "firebase",
      "title": "Special Offer!"
    },
    "telegram": {
      "transport": "telegram_bot"
    }
  },
  "context": {
    "campaign_id": "summer_sale_2025",
    "discount_code": "SAVE50",
    "expires_at": "2025-09-30T23:59:59Z"
  }
}
```

## Response

The endpoint returns the created notification with generated message details:

```json
{
  "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "type": "welcome",
  "importance": "normal",
  "subject": "Welcome to Our Platform!",
  "channels": ["email"],
  "context": {},
  "createdAt": "2025-09-19T10:30:00Z",
  "messages": [
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FB1",
      "type": "email",
      "status": "pending",
      "transportName": "default",
      "subject": "Welcome to Our Platform!",
      "fromEmail": "welcome@yourapp.com",
      "fromName": "Your App Team",
      "recipients": [
        {
          "id": "01ARZ3NDEKTSV4RRFFQ69G5FB2",
          "type": "to",
          "address": "newuser@example.com",
          "name": "New User",
          "status": "pending"
        }
      ],
      "content": {
        "id": "01ARZ3NDEKTSV4RRFFQ69G5FB3",
        "contentType": "text/html",
        "bodyHtml": "<h1>Welcome!</h1><p>Thank you for joining us.</p>"
      },
      "createdAt": "2025-09-19T10:30:00Z"
    }
  ],
  "totalMessages": 1,
  "messageStats": {
    "total": 1,
    "pending": 1,
    "sent": 0,
    "delivered": 0,
    "failed": 0,
    "queued": 0,
    "cancelled": 0
  }
}
```

## Benefits

1. **Unified Creation**: Create notifications for multiple channels in a single API call
2. **Automatic Message Generation**: The system automatically creates appropriate message entities
3. **Flexible Configuration**: Per-channel transport and formatting settings
4. **Rich Tracking**: Full tracking and analytics for all generated messages
5. **Webhook Integration**: All messages integrate with the webhook system for delivery tracking
6. **Scalable**: Messages are created with proper relationships for high-performance querying

## Next Steps

After creating a notification, you can:
- Track message delivery via the webhook endpoints
- Query message status via `/notification-tracker/messages`
- Retry failed messages via `/notification-tracker/messages/{id}/retry`
- View detailed analytics via the notification detail endpoint

This creates a complete notification workflow from creation to delivery tracking! 🚀
