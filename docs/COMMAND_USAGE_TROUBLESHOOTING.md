# Notification Tracker Bundle - Command Usage Fix

## Problem
The command `notification-tracker:create-notification` is failing with:
```
ArgumentCountError: Too few arguments to function CreateNotificationCommand::__construct(), 0 passed... and exactly 1 expected
```

## Solution

### 1. Clear Symfony Cache
```bash
cd /path/to/your/electrodiscount-tms-server
php bin/console cache:clear
php bin/console cache:clear --env=prod  # If using production
```

### 2. Verify Bundle Configuration
Make sure the bundle is properly configured in your `config/bundles.php`:

```php
<?php

return [
    // ... other bundles
    Nkamuo\NotificationTrackerBundle\NotificationTrackerBundle::class => ['all' => true],
];
```

### 3. Add Bundle Configuration (if needed)
Create or update `config/packages/notification_tracker.yaml`:

```yaml
notification_tracker:
    enabled: true
    tracking:
        enabled: true
    api:
        enabled: false  # Set to true if you want API endpoints
    analytics:
        enabled: false  # Set to true if you want analytics
    webhooks:
        enabled: false  # Set to true if you want webhook support
```

### 4. Verify Command Registration
Check if commands are available:
```bash
php bin/console list notification-tracker
```

You should see:
- `notification-tracker:create-notification`
- `notification-tracker:send-bulk-email`
- Other existing commands...

### 5. Test the Command
```bash
# Create a draft notification
php bin/console notification-tracker:create-notification \
  --draft \
  --to="user@example.com" \
  --cc="team@example.com" \
  --subject="Test Notification" \
  --body="This is a test notification"

# Send a bulk email
php bin/console notification-tracker:send-bulk-email \
  --to="user1@example.com,user2@example.com" \
  --subject="Bulk Test" \
  --body="Hello everyone"
```

### 6. If Still Not Working
The issue might be that the auto-registration isn't working properly. You can manually register the commands in your app by creating a custom service configuration.

Create `config/services.yaml` entry:
```yaml
services:
    # Manual command registration if auto-registration fails
    Nkamuo\NotificationTrackerBundle\Command\CreateNotificationCommand:
        arguments:
            $entityManager: '@doctrine.orm.entity_manager'
        tags:
            - { name: 'console.command' }
    
    Nkamuo\NotificationTrackerBundle\Command\SendEmailCommand:
        arguments:
            $messageBus: '@messenger.default_bus'
            $entityManager: '@doctrine.orm.entity_manager'
        tags:
            - { name: 'console.command' }
```

## Command Options Reference

### notification-tracker:create-notification
```bash
Options:
  --to=TO                    Recipient email addresses (comma-separated)
  --cc=CC                    CC email addresses (comma-separated)
  --bcc=BCC                  BCC email addresses (comma-separated)
  --subject=SUBJECT          Notification subject
  --body=BODY                Notification body content
  --html=HTML                HTML body content
  --channel=CHANNEL          Notification channel (default: email)
  --importance=IMPORTANCE    Importance level (low, normal, high, urgent)
  --template=TEMPLATE        Template identifier
  --campaign=CAMPAIGN        Campaign identifier
  --draft                    Save as draft instead of queueing for send
  --schedule=SCHEDULE        Schedule for future send (Y-m-d H:i:s format)
```

### notification-tracker:send-bulk-email
```bash
Options:
  --to=TO                    Recipient email addresses (comma-separated) [required]
  --cc=CC                    CC email addresses (comma-separated)
  --bcc=BCC                  BCC email addresses (comma-separated)
  --subject=SUBJECT          Email subject [required]
  --body=BODY                Email body content
  --html=HTML                HTML body content
  --from=FROM                Sender email address
  --importance=IMPORTANCE    Importance level (low, normal, high, urgent)
  --draft                    Save as draft instead of sending
  --schedule=SCHEDULE        Schedule for future send (Y-m-d H:i:s format)
```
