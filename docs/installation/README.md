# NotificationTrackerBundle - Route Configuration

## Overview

The NotificationTrackerBundle uses API Platform attributes on entities to define routes automatically. No manual route imports are needed if API Platform is properly configured.

## Step 1: Install the Bundle

```bash
composer require nkamuo/notification-tracker-bundle
```

## Step 2: Configure API Platform

Add the bundle's entities to your API Platform configuration in `config/packages/api_platform.yaml`:

```yaml
api_platform:
    mapping:
        paths:
            - '%kernel.project_dir%/src/Entity'
            - '%kernel.project_dir%/vendor/nkamuo/notification-tracker-bundle/src/Entity'
    
    # Optional: Customize documentation
    title: 'Your App API with Notification Tracking'
    description: 'API documentation including notification tracking endpoints'
    version: '1.0.0'
```

## Step 3: Configure the Bundle (Optional)

Create `config/packages/notification_tracker.yaml`:

```yaml
notification_tracker:
    enabled: true
    tracking:
        enabled: true
        store_content: true
    api:
        enabled: true
        docs_enabled: true
    webhooks:
        enabled: true
```

## Step 4: Update Database Schema

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Step 5: Verify Routes

```bash
# Check all routes
php bin/console debug:router | grep notification

# Expected routes:
# api_messages_get_collection              GET      /api/notification-tracker/messages
# api_messages_get                         GET      /api/notification-tracker/messages/{id}
# notification_tracker_retry_message       POST     /api/notification-tracker/messages/{id}/retry
# notification_tracker_cancel_message      POST     /api/notification-tracker/messages/{id}/cancel
# api_messages_delete                      DELETE   /api/notification-tracker/messages/{id}
```

## Step 6: Test the API

```bash
# List messages
curl http://localhost:8000/api/notification-tracker/messages

# Get single message (replace {id} with actual ULID)
curl http://localhost:8000/api/notification-tracker/messages/{id}

# Retry a message
curl -X POST http://localhost:8000/api/notification-tracker/messages/{id}/retry

# Cancel a message  
curl -X POST http://localhost:8000/api/notification-tracker/messages/{id}/cancel
```

## API Documentation

Once configured, visit `/api/docs` to see the interactive API documentation including all notification tracker endpoints.

## Troubleshooting

### Routes not appearing?

1. Make sure API Platform is installed: `composer require api-platform/api-platform`
2. Check that the bundle entities path is in your `api_platform.yaml` mapping
3. Clear cache: `php bin/console cache:clear`
4. Verify bundle is registered in `config/bundles.php`

### Permission issues?

The bundle doesn't include authentication/authorization by default. Add your own security configuration:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/api/notification-tracker, roles: ROLE_ADMIN }
```
