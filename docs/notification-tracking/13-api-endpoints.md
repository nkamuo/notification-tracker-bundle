# API Endpoints Documentation

This document describes the new API Platform endpoints for sending notifications through various channels.

## Overview

The notification tracker bundle now provides several API endpoints for sending notifications:

- `/api/send/email` - Send individual emails
- `/api/send/sms` - Send individual SMS messages  
- `/api/send/slack` - Send individual Slack messages
- `/api/send/notification` - Send multi-channel notifications
- `/api/notification_drafts` - Manage notification drafts (CRUD)
- `/api/notification_drafts/{id}/send` - Send a draft immediately
- `/api/notification_drafts/{id}/schedule` - Schedule a draft for later

## Individual Channel Endpoints

### Send Email - `POST /api/send/email`

Send an email with full tracking support.

```json
{
  "to": "user@example.com",
  "subject": "Test Email",
  "text": "Hello from the notification system!",
  "html": "<h1>Hello from the notification system!</h1>",
  "labels": ["marketing", "newsletter"],
  "save_as_draft": false,
  "scheduled_at": "2024-12-07T15:00:00Z"
}
```

**Advanced Recipients:**
```json
{
  "to": [
    "simple@example.com",
    {"email": "named@example.com", "name": "John Doe"}
  ],
  "cc": ["cc@example.com"],
  "bcc": ["bcc@example.com"],
  "subject": "Meeting Reminder",
  "text": "Don't forget about our meeting tomorrow."
}
```

### Send SMS - `POST /api/send/sms`

Send an SMS message with tracking.

```json
{
  "to": ["+1234567890", "+0987654321"],
  "message": "Your verification code is 123456",
  "labels": ["verification", "security"],
  "save_as_draft": false,
  "scheduled_at": "2024-12-07T15:00:00Z"
}
```

### Send Slack - `POST /api/send/slack`

Send a Slack message with rich formatting support.

```json
{
  "channel": "#general",
  "message": "Deployment completed successfully!",
  "blocks": [
    {
      "type": "section",
      "text": {
        "type": "mrkdwn", 
        "text": "*Deployment Status:* ✅ Success"
      }
    }
  ],
  "thread_ts": "1234567890.123456",
  "labels": ["deployment", "ops"],
  "save_as_draft": false,
  "scheduled_at": "2024-12-07T15:00:00Z"
}
```

## Multi-Channel Notification - `POST /api/send/notification`

Send notifications across multiple channels simultaneously.

```json
{
  "content": {
    "subject": "System Alert",
    "message": "Database backup completed successfully"
  },
  "labels": ["system", "backup"],
  "channels": [
    {
      "type": "email",
      "to": ["admin@example.com", "ops@example.com"],
      "html": "<h2>✅ Backup Complete</h2><p>Database backup finished at {{ timestamp }}</p>"
    },
    {
      "type": "sms", 
      "to": ["+1234567890"]
    },
    {
      "type": "slack",
      "channel": "#ops-alerts",
      "blocks": [
        {
          "type": "section",
          "text": {
            "type": "mrkdwn",
            "text": "🔄 *System Alert*\n✅ Database backup completed successfully"
          }
        }
      ]
    }
  ],
  "save_as_draft": false,
  "scheduled_at": "2024-12-07T15:00:00Z"
}
```

## Draft Management Endpoints

### Create Draft - `POST /api/notification_drafts`

```json
{
  "title": "Weekly Newsletter",
  "channels": ["email", "slack"],
  "emailRecipients": ["team@example.com"],
  "slackChannels": ["#announcements"],
  "content": {
    "subject": "This Week's Updates",
    "text": "Here are this week's highlights...",
    "html": "<h1>This Week's Updates</h1><p>Here are this week's highlights...</p>"
  },
  "labels": [{"name": "newsletter"}],
  "scheduledAt": "2024-12-07T09:00:00Z"
}
```

### Send Draft - `POST /api/notification_drafts/{id}/send`

```json
{
  "sendImmediately": true
}
```

### Schedule Draft - `PUT /api/notification_drafts/{id}/schedule`

```json
{
  "scheduledAt": "2024-12-07T15:00:00Z"
}
```

## Response Format

All endpoints return consistent response formats:

**Success Response:**
```json
{
  "success": true,
  "message_id": "01HKQJ7X8G9MNPQRSTUVWXYZ12",
  "status": "sent",
  "recipients_count": 3
}
```

**Multi-channel Response:**
```json
{
  "success": true,
  "status": "sent",
  "channels": [
    {
      "channel": "email",
      "success": true,
      "message_id": "01HKQJ7X8G9MNPQRSTUVWXYZ12",
      "status": "sent"
    },
    {
      "channel": "sms", 
      "success": true,
      "message_id": "01HKQJ7X8G9MNPQRSTUVWXYZ13",
      "status": "sent"
    }
  ],
  "summary": {
    "total": 2,
    "successful": 2,
    "failed": 0
  }
}
```

**Error Response:**
```json
{
  "error": "Field 'to' is required",
  "success": false
}
```

## Features

### Drafts and Scheduling

All endpoints support:
- `save_as_draft: true` - Save without sending
- `scheduled_at: "ISO8601"` - Schedule for future delivery

### Labeling System

Add labels for categorization and filtering:
```json
{
  "labels": ["marketing", "urgent", "customer-onboarding"]
}
```

### Message Tracking

Every sent message receives:
- Unique ULID identifier
- Status tracking (queued → sent → delivered → failed)
- Event timeline with webhooks
- Relationship linking for multi-channel sends

### API Platform Integration

- Full CRUD operations on drafts
- Pagination and filtering support  
- Standardized error responses
- OpenAPI documentation generation
- Built-in validation

## Usage Examples

### Emergency Alert System

```bash
curl -X POST /api/send/notification \
  -H "Content-Type: application/json" \
  -d '{
    "content": {
      "subject": "URGENT: System Outage",
      "message": "Payment processing is currently down. ETA for resolution: 15 minutes."
    },
    "labels": ["urgent", "outage", "payments"],
    "channels": [
      {
        "type": "email",
        "to": ["oncall@company.com", "management@company.com"]
      },
      {
        "type": "sms",
        "to": ["+1234567890", "+0987654321"]  
      },
      {
        "type": "slack",
        "channel": "#incidents",
        "blocks": [...]
      }
    ]
  }'
```

### Scheduled Marketing Campaign

```bash
curl -X POST /api/notification_drafts \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Black Friday Sale",
    "channels": ["email"],
    "emailRecipients": ["customers@company.com"],
    "content": {
      "subject": "🔥 50% Off Everything - Black Friday Sale!",
      "html": "<h1>Limited Time Offer...</h1>"
    },
    "labels": [{"name": "marketing"}, {"name": "black-friday"}],
    "scheduledAt": "2024-11-29T09:00:00Z"
  }'
```

This system provides a complete notification platform with API-first design, comprehensive tracking, and multi-channel delivery capabilities.
