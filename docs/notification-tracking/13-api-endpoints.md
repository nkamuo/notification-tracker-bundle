# API Endpoints Documentation

This document describes the unified API Platform endpoints for managing and sending notifications through various channels.

## Overview

The notification tracker bundle provides a unified notification system with these main endpoints:

- `/api/notifications` - Unified notification management (CRUD) with status-based workflow
- `/api/notifications/{id}/send` - Send a notification immediately
- `/api/notifications/{id}/schedule` - Schedule a notification for later delivery
- `/api/send/email` - Send individual emails
- `/api/send/sms` - Send individual SMS messages  
- `/api/send/slack` - Send individual Slack messages

## Unified Notification Management

### Create Notification - `POST /api/notifications`

Create a new notification that supports draft → scheduled → sent workflow:

```json
{
  "type": "marketing",
  "importance": "normal",
  "status": "draft",
  "direction": "outbound",
  "subject": "Weekly Newsletter",
  "channels": ["email", "slack"],
  "recipients": [
    {"channel": "email", "address": "team@example.com", "name": "Team"},
    {"channel": "slack", "address": "#announcements"}
  ],
  "content": "Here are this week's highlights...",
  "channelSettings": {
    "email": {
      "html": "<h1>This Week's Updates</h1><p>Here are this week's highlights...</p>"
    }
  },
  "metadata": {
    "campaign": "weekly_newsletter",
    "source": "api"
  }
}
```

### Send Notification - `POST /api/notifications/{id}/send`

Send a draft notification immediately:

```json
{
  "sendImmediately": true
}
```

### Schedule Notification - `PUT /api/notifications/{id}/schedule`

Schedule a notification for future delivery:

```json
{
  "scheduledAt": "2024-12-07T15:00:00Z"
}
```

### Get Notifications - `GET /api/notifications`

Filter notifications by status, direction, or other criteria:

```bash
# Get all draft notifications
GET /api/notifications?status=draft&direction=draft

# Get sent notifications from last week
GET /api/notifications?status=sent&createdAt[after]=2024-12-01

# Get notifications by type
GET /api/notifications?type=marketing
```

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
  "scheduled_at": "2024-12-07T15:00:00Z"
}
```

## Notification Workflow Examples

### Draft → Edit → Send Workflow

**Step 1: Create Draft**
```bash
POST /api/notifications
{
  "type": "newsletter",
  "status": "draft",
  "direction": "outbound",
  "subject": "Weekly Updates",
  "channels": ["email"],
  "recipients": [{"channel": "email", "address": "team@example.com"}],
  "content": "Draft content..."
}
```

**Step 2: Edit Draft** 
```bash
PUT /api/notifications/{id}
{
  "subject": "Weekly Updates - Updated",
  "content": "Final content..."
}
```

**Step 3: Send Immediately**
```bash
POST /api/notifications/{id}/send
{
  "sendImmediately": true
```

### Draft → Schedule → Auto-Send Workflow

**Step 1: Create and Schedule**
```bash
POST /api/notifications
{
  "type": "marketing",
  "status": "draft",
  "subject": "Black Friday Sale",
  "scheduledAt": "2024-11-29T09:00:00Z",
  "channels": ["email"],
  "recipients": [{"channel": "email", "address": "customers@example.com"}]
}
```

**Step 2: System automatically sends at scheduled time**
Status transitions: `draft` → `scheduled` → `queued` → `sending` → `sent`
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

### Status-Based Workflow

The unified notification system supports a comprehensive workflow:
- **draft** - Created but not sent
- **scheduled** - Queued for future delivery  
- **queued** - Ready for processing
- **sending** - Currently being delivered
- **sent** - Successfully delivered
- **failed** - Delivery failed

### Direction Types

- **outbound** - Notifications sent to external recipients
- **inbound** - Received notifications/webhooks
- **draft** - Draft notifications being composed

### Scheduling

Schedule notifications for future delivery:
```json
{
  "scheduledAt": "2024-12-07T15:00:00Z"
}
```

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

- Full CRUD operations on notifications
- Pagination and filtering support  
- Standardized error responses
- OpenAPI documentation generation
- Built-in validation

## Usage Examples

### Emergency Alert System

Send urgent notifications across multiple channels:

```bash
curl -X POST /api/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "type": "alert",
    "importance": "urgent", 
    "status": "draft",
    "direction": "outbound",
    "subject": "URGENT: System Outage",
    "content": "Payment processing is currently down. ETA for resolution: 15 minutes.",
    "channels": ["email", "sms", "slack"],
    "recipients": [
      {"channel": "email", "address": "oncall@company.com"},
      {"channel": "email", "address": "management@company.com"},
      {"channel": "sms", "address": "+1234567890"},
      {"channel": "slack", "address": "#incidents"}
    ],
    "metadata": {
      "severity": "high",
      "incident_id": "INC-2024-001"
    }
  }'

# Then send immediately
curl -X POST /api/notifications/{id}/send \
  -H "Content-Type: application/json" \
  -d '{"sendImmediately": true}'
```

### Scheduled Marketing Campaign

Create and schedule a marketing notification:

```bash
curl -X POST /api/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "type": "marketing",
    "importance": "normal",
    "status": "draft", 
    "direction": "outbound",
    "subject": "🔥 50% Off Everything - Black Friday Sale!",
    "content": "Limited time offer - dont miss out!",
    "channels": ["email"],
    "recipients": [
      {"channel": "email", "address": "customers@company.com"}
    ],
    "channelSettings": {
      "email": {
        "html": "<h1>Black Friday Sale</h1><p>Limited Time Offer...</p>"
      }
    },
    "scheduledAt": "2024-11-29T09:00:00Z",
    "metadata": {
      "campaign": "black-friday-2024",
      "source": "marketing-api"
    }
  }'
```

### Query and Filter Notifications

```bash
# Get all draft notifications
curl -X GET "/api/notifications?status=draft"

# Get marketing notifications from last month  
curl -X GET "/api/notifications?type=marketing&createdAt[after]=2024-11-01"

# Get failed notifications
curl -X GET "/api/notifications?status=failed"
```

This unified system provides a complete notification platform with API-first design, comprehensive tracking, and multi-channel delivery capabilities.
