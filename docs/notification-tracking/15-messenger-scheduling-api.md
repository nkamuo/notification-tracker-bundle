# Messenger-Based Notification Scheduling API

This document describes the enhanced notification system that leverages **Symfony Messenger's DelayStamp** for robust, distributed scheduling with individual message timing control.

## 🎯 Architecture Overview

### **Messenger-Driven Scheduling**
- **Notification Level**: Uses `DelayStamp` for notification-wide scheduling
- **Message Level**: Individual messages can override with their own `DelayStamp`
- **Transport Integration**: Built-in support via `NotificationTrackingTransport`
- **Failure Handling**: Automatic retries with exponential backoff
- **Clear Visibility**: See exactly when each message will be sent

### **Key Components**
1. **SendNotificationMessage** - Dispatches notifications through messenger
2. **SendChannelMessage** - Handles individual channel delivery with scheduling
3. **Message Handlers** - Process delivery with DelayStamp awareness
4. **Transport Layer** - Manages queue persistence and timing

## 📋 Enhanced API Endpoints

### Send with Individual Message Scheduling

**Create Notification with Per-Channel Timing:**
```bash
curl -X POST /api/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "type": "multi_channel_campaign",
    "subject": "Product Launch Announcement",
    "content": "Exciting news about our new product!",
    "channels": ["email", "sms", "slack"],
    "recipients": [
      {
        "email": "customers@company.com",
        "channel": "email",
        "scheduledAt": "2025-09-22T09:00:00Z"
      },
      {
        "phone": "+1234567890", 
        "channel": "sms",
        "scheduledAt": "2025-09-22T14:00:00Z"
      },
      {
        "channel": "#announcements",
        "channel": "slack",
        "scheduledAt": "2025-09-23T10:00:00Z"
      }
    ],
    "status": "draft",
    "direction": "draft",
    "metadata": {
      "source": "user",
      "campaign_id": "launch_2025_q3"
    }
  }'
```

**Response:**
```json
{
  "id": "01HKQJ7X8G9MNPQRSTUVWXYZ12",
  "status": "draft",
  "direction": "draft", 
  "subject": "Product Launch Announcement",
  "channels": ["email", "sms", "slack"],
  "recipients": [
    {
      "email": "customers@company.com",
      "channel": "email", 
      "scheduledAt": "2025-09-22T09:00:00Z",
      "effective_delay_ms": 75600000
    },
    {
      "phone": "+1234567890",
      "channel": "sms",
      "scheduledAt": "2025-09-22T14:00:00Z", 
      "effective_delay_ms": 93600000
    },
    {
      "channel": "#announcements",
      "channel": "slack",
      "scheduledAt": "2025-09-23T10:00:00Z",
      "effective_delay_ms": 166800000
    }
  ]
}
```

### Send with Mixed Timing

**Send Immediately + Schedule Some Later:**
```bash
curl -X POST /api/notifications/01HKQJ7X8G9MNPQRSTUVWXYZ12/send
```

**Response:**
```json
{
  "success": true,
  "notification_id": "01HKQJ7X8G9MNPQRSTUVWXYZ12",
  "status": "queued",
  "scheduled": true,
  "delay_ms": 0,
  "message": "Notification queued for immediate delivery",
  "scheduling_details": {
    "immediate_channels": [],
    "scheduled_channels": [
      {
        "channel": "email",
        "scheduled_at": "2025-09-22T09:00:00Z",
        "delay_ms": 75600000,
        "message_id": "01HKQJ7X8G9MNPQRSTUVWXYZ13"
      },
      {
        "channel": "sms", 
        "scheduled_at": "2025-09-22T14:00:00Z",
        "delay_ms": 93600000,
        "message_id": "01HKQJ7X8G9MNPQRSTUVWXYZ14"
      },
      {
        "channel": "slack",
        "scheduled_at": "2025-09-23T10:00:00Z", 
        "delay_ms": 166800000,
        "message_id": "01HKQJ7X8G9MNPQRSTUVWXYZ15"
      }
    ]
  }
}
```

## 🔄 Complete Workflow Examples

### 1. Same Content, Different Timing Per Channel

**Use Case**: Email newsletter at 9 AM, SMS reminder at 2 PM, Slack update next day

```bash
# Step 1: Create with individual scheduling
curl -X POST /api/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "type": "newsletter",
    "subject": "Weekly Team Update",
    "content": "This week'\''s highlights and upcoming events",
    "channels": ["email", "sms", "slack"],
    "recipients": [
      {
        "email": "team@company.com",
        "channel": "email",
        "scheduledAt": "2025-09-22T09:00:00Z"
      },
      {
        "phone": "+1234567890",
        "channel": "sms", 
        "scheduledAt": "2025-09-22T14:00:00Z"
      },
      {
        "channel": "#general",
        "channel": "slack",
        "scheduledAt": "2025-09-23T10:00:00Z"
      }
    ],
    "status": "draft"
  }' | jq '.id' | xargs -I {} \
curl -X POST /api/notifications/{}/send
```

### 2. Override Notification-Level Scheduling

**Notification scheduled for 3 PM, but SMS goes out at 5 PM:**

```bash
curl -X PUT /api/notifications/01HKQJ7X8G9MNPQRSTUVWXYZ12/schedule \
  -H "Content-Type: application/json" \
  -d '{
    "scheduledAt": "2025-09-22T15:00:00Z",
    "recipientOverrides": [
      {
        "phone": "+1234567890",
        "channel": "sms",
        "scheduledAt": "2025-09-22T17:00:00Z"
      }
    ]
  }'
```

**Result**: Email sends at 3 PM (notification default), SMS sends at 5 PM (override)

### 3. ASAP vs Scheduled Visibility

**Check Message Queue Status:**
```bash
# See what's scheduled vs immediate
curl -X GET "/api/messages?notification.id=01HKQJ7X8G9MNPQRSTUVWXYZ12&status=pending"
```

**Response shows clear scheduling:**
```json
{
  "data": [
    {
      "id": "01HKQJ7X8G9MNPQRSTUVWXYZ13",
      "channel": "email",
      "status": "pending",
      "scheduled_at": "2025-09-22T09:00:00Z",
      "effective_scheduled_at": "2025-09-22T09:00:00Z",
      "has_schedule_override": true,
      "ready_to_send": false,
      "delay_remaining_ms": 75600000,
      "delivery_method": "messenger_delay_stamp"
    },
    {
      "id": "01HKQJ7X8G9MNPQRSTUVWXYZ14", 
      "channel": "sms",
      "status": "pending",
      "scheduled_at": "2025-09-22T14:00:00Z",
      "effective_scheduled_at": "2025-09-22T14:00:00Z", 
      "has_schedule_override": true,
      "ready_to_send": false,
      "delay_remaining_ms": 93600000,
      "delivery_method": "messenger_delay_stamp"
    }
  ]
}
```

## ⚡ Messenger Transport Configuration

**Configure the enhanced transport:**

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            notification_tracking:
                dsn: 'notification-tracking://default'
                options:
                    transport_name: 'notification'
                    queue_name: 'notifications'
                    analytics_enabled: true
                    max_retries: 3
                    retry_delays: [1000, 5000, 30000] # ms
                    batch_size: 10
                
        routing:
            'Nkamuo\NotificationTrackerBundle\Message\SendNotificationMessage': notification_tracking
            'Nkamuo\NotificationTrackerBundle\Message\SendChannelMessage': notification_tracking
```

## 🔍 Enhanced Visibility

### Scheduling Status Dashboard

**Get Notification with Timing Details:**
```bash
curl -X GET "/api/notifications/01HKQJ7X8G9MNPQRSTUVWXYZ12?include=messages.scheduling"
```

**Response:**
```json
{
  "id": "01HKQJ7X8G9MNPQRSTUVWXYZ12",
  "subject": "Product Launch Announcement",
  "status": "queued",
  "scheduled_at": null,
  "messages": [
    {
      "id": "01HKQJ7X8G9MNPQRSTUVWXYZ13",
      "channel": "email",
      "status": "pending",
      "scheduled_at": "2025-09-22T09:00:00Z",
      "has_schedule_override": true,
      "ready_to_send": false,
      "in_messenger_queue": true,
      "delay_stamp_ms": 75600000
    },
    {
      "id": "01HKQJ7X8G9MNPQRSTUVWXYZ14",
      "channel": "sms", 
      "status": "pending",
      "scheduled_at": "2025-09-22T14:00:00Z",
      "has_schedule_override": true,
      "ready_to_send": false,
      "in_messenger_queue": true,
      "delay_stamp_ms": 93600000
    }
  ]
}
```

### Queue Monitoring

**See Delayed Messages in Transport:**
```bash
curl -X GET "/api/transport/notification_tracking/stats"
```

**Response:**
```json
{
  "transport": "notification_tracking",
  "queue_stats": {
    "immediate": 0,
    "delayed": 3,
    "processing": 0,
    "failed": 0
  },
  "delayed_messages": [
    {
      "message_id": "01HKQJ7X8G9MNPQRSTUVWXYZ13",
      "available_at": "2025-09-22T09:00:00Z",
      "delay_remaining_ms": 75600000,
      "message_type": "SendChannelMessage"
    },
    {
      "message_id": "01HKQJ7X8G9MNPQRSTUVWXYZ14",
      "available_at": "2025-09-22T14:00:00Z",
      "delay_remaining_ms": 93600000,
      "message_type": "SendChannelMessage"
    }
  ]
}
```

## 🎪 Benefits Summary

✅ **Native Messenger Integration** - Uses DelayStamp for reliable scheduling
✅ **Individual Message Control** - Each channel can have its own timing
✅ **Transport-Level Persistence** - Survives server restarts
✅ **Failure Recovery** - Automatic retries with exponential backoff  
✅ **Clear Visibility** - See exactly when messages will be delivered
✅ **ASAP vs Scheduled** - Immediate delivery or precise timing
✅ **Distributed Processing** - Works across multiple workers
✅ **Monitoring Ready** - Full visibility into queue status

The system now provides **crystal-clear scheduling control**: send emails overnight, SMS during business hours, and Slack updates the next day - all from a single notification with individual message timing! 🚀
