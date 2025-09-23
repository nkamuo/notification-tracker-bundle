# 🚀 Notification Tracker Commands - Complete Guide

Welcome to the comprehensive command-line interface for testing and managing your notification tracker transport!

## 📋 Available Commands

### 1. 📧 Email Commands

#### Send Test Email
```bash
# Interactive email sender
php bin/console notification-tracker:send-email user@example.com

# With all options
php bin/console notification-tracker:send-email user@example.com \
  --from="sender@yourdomain.com" \
  --subject="Test Email" \
  --body="Test message body" \
  --html="<p>HTML body</p>" \
  --priority=8 \
  --campaign="welcome-series" \
  --template="welcome-email" \
  --provider="email" \
  --async
```

**Options:**
- `--from`: Sender email address
- `--subject`: Email subject line
- `--body`: Plain text body
- `--html`: HTML body content
- `--priority`: Message priority (1-10)
- `--campaign`: Campaign identifier for tracking
- `--template`: Template identifier
- `--provider`: Email provider type
- `--async`: Send through transport queue (recommended)

### 2. 📱 SMS Commands

#### Send Test SMS
```bash
# Interactive SMS sender
php bin/console notification-tracker:send-sms "+1234567890"

# With all options
php bin/console notification-tracker:send-sms "+1234567890" \
  --from="YourApp" \
  --message="Test SMS message" \
  --priority=7 \
  --campaign="sms-alerts" \
  --template="alert-sms" \
  --provider="sms" \
  --async
```

**Options:**
- `--from`: Sender name or phone number
- `--message`: SMS text content
- `--priority`: Message priority (1-10)
- `--campaign`: Campaign identifier
- `--template`: Template identifier
- `--provider`: SMS provider type
- `--async`: Send through transport queue

### 3. 💬 Chat Commands

#### Send Test Chat Message
```bash
# Interactive chat sender
php bin/console notification-tracker:send-chat "Hello from notification tracker!"

# With all options
php bin/console notification-tracker:send-chat "Meeting reminder" \
  --channel="#general" \
  --subject="Important Meeting" \
  --priority=6 \
  --campaign="team-notifications" \
  --template="meeting-reminder" \
  --provider="slack" \
  --async
```

**Options:**
- `--channel`: Chat channel or room
- `--subject`: Message title/subject
- `--priority`: Message priority (1-10)
- `--campaign`: Campaign identifier
- `--template`: Template identifier
- `--provider`: Chat provider (slack, teams, discord)
- `--async`: Send through transport queue

### 4. 📲 Push Notification Commands

#### Send Test Push Notification
```bash
# Interactive push sender
php bin/console notification-tracker:send-push "App Update" "New features available!"

# With all options
php bin/console notification-tracker:send-push "Breaking News" "Important update available" \
  --priority=9 \
  --campaign="app-updates" \
  --template="update-notification" \
  --provider="firebase" \
  --async
```

**Options:**
- `--priority`: Message priority (1-10)
- `--campaign`: Campaign identifier
- `--template`: Template identifier
- `--provider`: Push provider (firebase, apns)
- `--async`: Send through transport queue

### 5. 🎯 Universal Notification Command

#### Interactive Notification Sender
```bash
# Fully interactive - choose type and options
php bin/console notification-tracker:send-notification

# Pre-configure type
php bin/console notification-tracker:send-notification \
  --type=email \
  --async \
  --campaign="test-campaign" \
  --priority=8
```

**Options:**
- `--type`: Notification type (email, sms, chat, push)
- `--async`: Send through transport queue
- `--campaign`: Campaign identifier
- `--template`: Template identifier
- `--priority`: Message priority (1-10)

### 6. 📊 Queue Management Commands

#### Queue Status Monitor
```bash
# Show current queue status
php bin/console notification-tracker:queue-status

# Watch mode (auto-refresh every 5 seconds)
php bin/console notification-tracker:queue-status --watch

# Detailed view with message information
php bin/console notification-tracker:queue-status --details

# Show last 20 messages
php bin/console notification-tracker:queue-status --limit=20
```

**Options:**
- `--watch`: Auto-refresh mode
- `--details`: Show detailed message information
- `--limit`: Number of recent messages to show

### 7. ⚙️ Queue Consumer

#### Start Message Processing
```bash
# Start consuming notification transport queue
php bin/console messenger:consume notification -vv

# With memory limit and time limit
php bin/console messenger:consume notification --memory-limit=128M --time-limit=3600

# Multiple transports
php bin/console messenger:consume notification async -vv
```

## 🔄 Complete Testing Workflow

### Step 1: Send Messages
```bash
# Send various types of notifications
php bin/console notification-tracker:send-email test@example.com --async
php bin/console notification-tracker:send-sms "+1234567890" --async
php bin/console notification-tracker:send-chat "Test message" --async
php bin/console notification-tracker:send-push "Test" "Push message" --async
```

### Step 2: Check Queue
```bash
# Monitor queue status
php bin/console notification-tracker:queue-status --watch
```

### Step 3: Process Queue
```bash
# Start consumer to process messages
php bin/console messenger:consume notification -vv
```

### Step 4: Verify Results
```bash
# Check API endpoints
curl http://localhost:8001/api/notification-tracker/queue/messages
curl http://localhost:8001/api/notification-tracker/messages
curl http://localhost:8001/api/notification-tracker/queue/stats
```

## 🎯 Testing Scenarios

### Scenario 1: High Priority Email Campaign
```bash
php bin/console notification-tracker:send-email urgent@example.com \
  --subject="URGENT: System Maintenance" \
  --priority=10 \
  --campaign="maintenance-alerts" \
  --template="maintenance-email" \
  --async
```

### Scenario 2: SMS Alert Batch
```bash
# Send multiple SMS alerts
for i in {1..5}; do
  php bin/console notification-tracker:send-sms "+123456789$i" \
    --message="Alert #$i: Test message" \
    --campaign="batch-alerts" \
    --async
done
```

### Scenario 3: Multi-Channel Notification
```bash
# Send same message across multiple channels
CAMPAIGN="multi-channel-test"
MESSAGE="Important system update available"

php bin/console notification-tracker:send-email user@example.com \
  --subject="$MESSAGE" --campaign="$CAMPAIGN" --async

php bin/console notification-tracker:send-sms "+1234567890" \
  --message="$MESSAGE" --campaign="$CAMPAIGN" --async

php bin/console notification-tracker:send-chat "$MESSAGE" \
  --campaign="$CAMPAIGN" --async

php bin/console notification-tracker:send-push "Update" "$MESSAGE" \
  --campaign="$CAMPAIGN" --async
```

## 🔍 Monitoring & Debugging

### Real-time Queue Monitoring
```bash
# Terminal 1: Watch queue status
php bin/console notification-tracker:queue-status --watch

# Terminal 2: Send messages
php bin/console notification-tracker:send-notification --async

# Terminal 3: Process queue
php bin/console messenger:consume notification -vv
```

### API Monitoring
```bash
# Check queue health
curl -s http://localhost:8001/api/notification-tracker/queue/health | jq

# Monitor queue messages
watch -n 2 'curl -s http://localhost:8001/api/notification-tracker/queue/messages | jq ".[0:5]"'

# Check message statistics
curl -s http://localhost:8001/api/notification-tracker/queue/stats | jq
```

## 🚨 Troubleshooting

### Queue Not Processing?
1. Check if consumer is running: `php bin/console messenger:consume notification -vv`
2. Verify transport configuration in messenger.yaml
3. Check database connectivity
4. Monitor logs for errors

### Messages Not Appearing in Queue?
1. Ensure you're using `--async` flag
2. Verify routing configuration in messenger.yaml
3. Check if transport DSN is correct
4. Confirm stamps are properly applied

### Transport Errors?
1. Check transport provider configuration
2. Verify credentials and API keys
3. Monitor provider-specific logs
4. Test with direct sending first

## 🎉 Success Indicators

✅ **Queue working**: Messages appear in queue status
✅ **Consumer working**: Messages processed and status changes
✅ **Tracking working**: Messages appear in API endpoints
✅ **Transport working**: Messages sent via configured providers
✅ **Monitoring working**: Real-time queue status updates

## 📚 Next Steps

1. **Configure Transport Providers**: Set up your actual email/SMS/push providers
2. **Create Custom Templates**: Build reusable message templates
3. **Set Up Webhooks**: Configure provider webhooks for delivery tracking
4. **Monitor Production**: Use queue status commands in production
5. **Scale Consumers**: Run multiple consumers for high-volume scenarios

**Your notification tracker is now fully equipped with robust testing and monitoring tools!** 🚀
