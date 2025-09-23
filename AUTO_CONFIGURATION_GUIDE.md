# 🚀 Notification Tracker Auto-Configuration Guide

The Notification Tracker Bundle automatically configures Symfony Messenger transports for tracking notifications. This guide explains how to use and customize the auto-configuration.

## 📦 Automatic Installation

When you install the bundle via Composer, Symfony Flex automatically:

1. ✅ **Adds bundle to bundles.php**
2. ✅ **Creates default configuration** (`config/packages/notification_tracker.yaml`)
3. ✅ **Sets environment variables** in `.env`
4. ✅ **Creates sample messenger config** (`config/packages/messenger.yaml`)
5. ✅ **Configures messenger transports** automatically

## 🔧 Environment Variables

The bundle adds these environment variables to your `.env` file:

```bash
# Main notification transport (all notification types)
MESSENGER_TRANSPORT_NOTIFICATION_DSN=notification-tracking://doctrine?transport_name=notification&analytics_enabled=true&provider_aware_routing=true

# Optional: Dedicated email transport for high-volume email
MESSENGER_TRANSPORT_NOTIFICATION_EMAIL_DSN=notification-tracking://doctrine?transport_name=email&analytics_enabled=true&provider_aware_routing=true&batch_size=25&max_retries=5
```

## ⚙️ Auto-Configuration Options

### **Basic Setup (Default)**
```yaml
notification_tracker:
    messenger:
        auto_configure: true  # Automatically configure transports
        
        transports:
            notification:
                enabled: true  # Creates 'notification' transport
```

### **Email-Specific Transport**
```yaml
notification_tracker:
    messenger:
        transports:
            notification_email:
                enabled: true  # Creates separate 'notification_email' transport
                batch_size: 25
                max_retries: 5
```

### **Auto-Route Channels**
```yaml
notification_tracker:
    messenger:
        auto_configure_channels:
            email: true      # Route Symfony Mailer through notification transport
            sms: true        # Route SMS notifications through notification transport
            push: true       # Route push notifications through notification transport
```

## 🎯 What Gets Auto-Configured

### **1. Messenger Transports**
The bundle automatically adds to your `framework.messenger.transports`:

```yaml
framework:
    messenger:
        transports:
            # Auto-added by the bundle:
            notification: "%env(MESSENGER_TRANSPORT_NOTIFICATION_DSN)%"
            notification_email: "%env(MESSENGER_TRANSPORT_NOTIFICATION_EMAIL_DSN)%"  # if enabled
```

### **2. Message Routing**
When `auto_configure_channels` is enabled:

```yaml
framework:
    messenger:
        routing:
            # Auto-added routing:
            'Symfony\Component\Mailer\Messenger\SendEmailMessage': notification_email
            'Symfony\Component\Notifier\Message\MessageInterface': notification
```

## 🚀 Usage Examples

### **Example 1: Basic Notification Tracking**
```yaml
# config/packages/notification_tracker.yaml
notification_tracker:
    messenger:
        auto_configure: true
        auto_configure_channels:
            email: true
```

**Result:** All emails automatically tracked through notification transport.

### **Example 2: High-Volume Email Setup**
```yaml
notification_tracker:
    messenger:
        transports:
            notification:
                enabled: true
                batch_size: 10
            notification_email:
                enabled: true
                batch_size: 50      # Higher throughput for emails
                max_retries: 5
        auto_configure_channels:
            email: true
```

**Result:** Emails use dedicated high-performance transport.

### **Example 3: Manual Configuration**
```yaml
notification_tracker:
    messenger:
        auto_configure: false  # Disable auto-configuration
```

Then manually configure in `config/packages/messenger.yaml`:
```yaml
framework:
    messenger:
        transports:
            my_notifications: "%env(MESSENGER_TRANSPORT_NOTIFICATION_DSN)%"
        routing:
            'App\Message\MyNotification': my_notifications
```

## 📊 Transport DSN Parameters

You can customize the transport behavior via DSN parameters:

```bash
# Basic transport
MESSENGER_TRANSPORT_NOTIFICATION_DSN=notification-tracking://doctrine

# With custom parameters
MESSENGER_TRANSPORT_NOTIFICATION_DSN=notification-tracking://doctrine?transport_name=email&analytics_enabled=true&provider_aware_routing=true&batch_size=25&max_retries=5&retry_delays=2000,10000,60000
```

### **Available Parameters:**
| Parameter | Default | Description |
|-----------|---------|-------------|
| `transport_name` | `notification` | Transport identifier |
| `queue_name` | `default` | Internal queue name |
| `analytics_enabled` | `true` | Enable analytics collection |
| `provider_aware_routing` | `false` | Route by notification provider |
| `batch_size` | `10` | Messages processed per batch |
| `max_retries` | `3` | Maximum retry attempts |
| `retry_delays` | `1000,5000,30000` | Comma-separated delays in ms |

## 🔍 Troubleshooting

### **Check Auto-Configuration Status**
```bash
# See what transports were configured
php bin/console debug:messenger

# Check bundle configuration
php bin/console debug:config notification_tracker messenger
```

### **Disable Auto-Configuration**
```yaml
notification_tracker:
    messenger:
        auto_configure: false
```

### **Override Transport Settings**
```yaml
notification_tracker:
    messenger:
        transports:
            notification:
                dsn: 'custom://transport'  # Override default DSN
```

## 🎉 Benefits

✅ **Zero Configuration** - Works out of the box  
✅ **Flexible** - Easy to customize and override  
✅ **Performance** - Optimized defaults for different use cases  
✅ **Analytics** - Automatic tracking and monitoring  
✅ **Production Ready** - Battle-tested configurations  

Your notification system is now automatically configured and ready for production! 🚀
