# Unified Notification API Documentation

This document describes the unified notification system API using a single `Notification` entity with status-based workflows.

## 🎯 Architecture Overview

The system uses a **unified approach** with:
- **Single Notification Entity** - Handles all workflows (drafts, scheduling, sending)
- **Status-Based Workflow** - `draft` → `scheduled` → `queued` → `sending` → `sent`
- **Direction Types** - `inbound`, `outbound`, `draft`
- **Metadata for Source Tracking** - `source: user|system|api`

## 📋 Core API Endpoints

### Notification CRUD Operations

**Create Draft Notification**
```http
POST /api/notifications
Content-Type: application/json

{
  "type": "newsletter",
  "subject": "Weekly Updates",
  "content": "Here are this week's highlights...",
  "channels": ["email", "slack"],
  "recipients": [
    {"email": "team@example.com", "channel": "email"},
    {"channel": "#announcements", "channel": "slack"}
  ],
  "status": "draft",
  "direction": "draft",
  "metadata": {
    "source": "user",
    "html_content": "<h1>Weekly Updates</h1><p>Content...</p>",
    "slack_blocks": [...]
  }
}
```

**List Notifications**
```http
GET /api/notifications?status=draft&direction=draft
```

**Get Notification Details**
```http
GET /api/notifications/{id}
```

**Update Notification**
```http
PUT /api/notifications/{id}
Content-Type: application/json

{
  "subject": "Updated Subject",
  "content": "Updated content...",
  "status": "draft"
}
```

### Workflow Operations

**Send Notification Immediately**
```http
POST /api/notifications/{id}/send
```

**Schedule Notification**
```http
PUT /api/notifications/{id}/schedule
Content-Type: application/json

{
  "scheduledAt": "2024-12-07T15:00:00Z"
}
```

## 🔄 Complete Workflow Examples

### 1. Draft → Edit → Send Workflow (UI-driven)

**Step 1: Create Draft**
```bash
curl -X POST /api/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "type": "marketing",
    "subject": "New Product Launch",
    "content": "Exciting news about our new product!",
    "channels": ["email", "sms", "slack"],
    "recipients": [
      {"email": "customers@company.com", "channel": "email"},
      {"phone": "+1234567890", "channel": "sms"},
      {"channel": "#marketing", "channel": "slack"}
    ],
    "status": "draft",
    "direction": "draft",
    "metadata": {
      "source": "user",
      "html_content": "<h1>New Product Launch</h1><p>Details...</p>"
    }
  }'
```

**Response:**
```json
{
  "id": "01HKQJ7X8G9MNPQRSTUVWXYZ12",
  "status": "draft",
  "direction": "draft",
  "subject": "New Product Launch",
  "created_at": "2024-12-06T10:00:00Z"
}
```

**Step 2: Edit Draft**
```bash
curl -X PUT /api/notifications/01HKQJ7X8G9MNPQRSTUVWXYZ12 \
  -H "Content-Type: application/json" \
  -d '{
    "subject": "NEW: Revolutionary Product Launch 🚀",
    "content": "Updated exciting news about our revolutionary new product!"
  }'
```

**Step 3: Send Immediately**
```bash
curl -X POST /api/notifications/01HKQJ7X8G9MNPQRSTUVWXYZ12/send
```

**Response:**
```json
{
  "success": true,
  "notification_id": "01HKQJ7X8G9MNPQRSTUVWXYZ12",
  "status": "sent",
  "channels": [
    {
      "channel": "email",
      "success": true,
      "sent_count": 1,
      "message_ids": ["01HKQJ7X8G9MNPQRSTUVWXYZ13"]
    },
    {
      "channel": "sms",
      "success": true,
      "sent_count": 1,
      "message_ids": ["01HKQJ7X8G9MNPQRSTUVWXYZ14"]
    },
    {
      "channel": "slack",
      "success": true,
      "sent_count": 1,
      "message_ids": ["01HKQJ7X8G9MNPQRSTUVWXYZ15"]
    }
  ],
  "summary": {
    "total_channels": 3,
    "successful_channels": 3,
    "failed_channels": 0
  }
}
```

### 2. Draft → Schedule → Auto-Send Workflow

**Step 1: Create and Schedule**
```bash
curl -X POST /api/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "type": "reminder",
    "subject": "Meeting Reminder",
    "content": "Don\'t forget about our team meeting tomorrow at 2 PM",
    "channels": ["email", "slack"],
    "recipients": [
      {"email": "team@company.com", "channel": "email"},
      {"channel": "#general", "channel": "slack"}
    ],
    "status": "draft",
    "direction": "draft",
    "metadata": {"source": "user"}
  }' | jq '.id' | xargs -I {} \
curl -X PUT /api/notifications/{}/schedule \
  -H "Content-Type: application/json" \
  -d '{"scheduledAt": "2024-12-07T13:00:00Z"}'
```

**Step 2: Background Processing (Cron)**
```bash
# This runs automatically via cron
bin/console notification-tracker:send-scheduled
```

### 3. System-Generated Notification (Automated)

**Business Event Trigger:**
```bash
curl -X POST /api/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "type": "user_welcome",
    "subject": "Welcome to Our Platform!",
    "content": "Thanks for joining us. Here\'s how to get started...",
    "channels": ["email", "sms"],
    "recipients": [
      {"email": "newuser@example.com", "channel": "email"},
      {"phone": "+1234567890", "channel": "sms"}
    ],
    "status": "queued",
    "direction": "outbound",
    "metadata": {
      "source": "system",
      "user_id": "01HKQJ7X8G9MNPQRSTUVWXYZ99",
      "trigger": "user_registration"
    }
  }' | jq '.id' | xargs -I {} \
curl -X POST /api/notifications/{}/send
```

### 4. Inbound Notification (Webhook)

**Webhook Received:**
```bash
curl -X POST /api/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "type": "email_bounce",
    "subject": "Email Delivery Failed",
    "content": "Message bounced: Invalid email address",
    "status": "received",
    "direction": "inbound",
    "metadata": {
      "source": "webhook",
      "provider": "sendgrid",
      "original_message_id": "01HKQJ7X8G9MNPQRSTUVWXYZ98",
      "bounce_reason": "invalid_email"
    }
  }'
```

## 📊 Status Management

### Status Flow
```
draft → scheduled → queued → sending → sent
  ↓         ↓         ↓        ↓       ↓
failed ←──────────────────────────────┘
  ↓
cancelled
```

### Status-Based Queries

**Get All Drafts:**
```http
GET /api/notifications?status=draft
```

**Get Scheduled Notifications:**
```http
GET /api/notifications?status=scheduled&scheduledAt[before]=2024-12-07T15:00:00Z
```

**Get Failed Notifications:**
```http
GET /api/notifications?status=failed&createdAt[after]=2024-12-06T00:00:00Z
```

## 🏷️ Metadata Usage Examples

### Source Tracking
```json
{
  "metadata": {
    "source": "user",         // user|system|api
    "created_by": "user_123",
    "department": "marketing"
  }
}
```

### Channel-Specific Data
```json
{
  "metadata": {
    "html_content": "<h1>Rich HTML for emails</h1>",
    "slack_blocks": [
      {
        "type": "section",
        "text": {"type": "mrkdwn", "text": "*Bold* formatting for Slack"}
      }
    ],
    "sms_short_url": "https://short.ly/abc123"
  }
}
```

### Business Context
```json
{
  "metadata": {
    "campaign_id": "campaign_456",
    "ab_test_variant": "B",
    "priority": "high",
    "tracking_params": {
      "utm_source": "email",
      "utm_campaign": "product_launch"
    }
  }
}
```

## 🔍 Message Tracking

Every sent notification creates tracked `Message` entities:

**Get Messages for Notification:**
```http
GET /api/messages?notification.id=01HKQJ7X8G9MNPQRSTUVWXYZ12
```

**Message Response:**
```json
{
  "data": [
    {
      "id": "01HKQJ7X8G9MNPQRSTUVWXYZ13",
      "type": "email",
      "status": "delivered",
      "direction": "outbound",
      "notification": {
        "id": "01HKQJ7X8G9MNPQRSTUVWXYZ12",
        "subject": "New Product Launch"
      },
      "recipients": [
        {
          "address": "customer@example.com",
          "status": "delivered"
        }
      ],
      "events": [
        {"type": "queued", "occurred_at": "2024-12-06T10:00:00Z"},
        {"type": "sent", "occurred_at": "2024-12-06T10:00:05Z"},
        {"type": "delivered", "occurred_at": "2024-12-06T10:00:15Z"}
      ]
    }
  ]
}
```

## 🎪 Summary

This unified approach provides:

✅ **Single Entity** - No duplicate `NotificationDraft` complexity
✅ **Clear Direction** - `inbound|outbound|draft` covers all flows  
✅ **Flexible Metadata** - Source tracking and custom data
✅ **Status Workflow** - Natural progression through states
✅ **API Platform** - Full CRUD + custom operations
✅ **Multi-Channel** - Email, SMS, Slack support
✅ **Comprehensive Tracking** - Every message tracked with events
✅ **Background Processing** - Scheduled delivery via cron
✅ **Backward Compatible** - Existing code continues working

The system now supports your complete workflow: "create and send new notification with this system (in which I can select which channels to use or all channels)" with drafts, scheduling, and comprehensive tracking! 🚀
