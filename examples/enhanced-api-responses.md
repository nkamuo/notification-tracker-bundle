# Enhanced API Response Examples

## Before vs After Comparison

### 🎯 BEFORE (v0.1.15): Basic API Response

**GET `/notification-tracker/notifications?page=1`**
```json
[
  {
    "id": "01K5HHVWSFNF660WQPHGYFNTTC",
    "type": "auto_generated",
    "importance": "normal",
    "createdAt": "2025-09-19T17:55:31+00:00"
  }
]
```

**Issues:**
- ❌ Minimal information
- ❌ No pagination controls
- ❌ No message statistics
- ❌ No engagement metrics
- ❌ No filtering capabilities

---

### 🚀 AFTER (v0.1.16): Enhanced API Response

**GET `/notification-tracker/notifications?page=1&itemsPerPage=5`**
```json
{
  "@context": "/api/contexts/Notification",
  "@id": "/api/notification-tracker/notifications",
  "@type": "hydra:Collection",
  "hydra:member": [
    {
      "@id": "/api/notification-tracker/notifications/01K5HHVWSFNF660WQPHGYFNTTC",
      "@type": "Notification",
      "id": "01K5HHVWSFNF660WQPHGYFNTTC",
      "type": "auto_generated",
      "importance": "normal",
      "channels": ["email"],
      "subject": "[Email] Welcome to our service!",
      "userId": null,
      "createdAt": "2025-09-19T17:55:31+00:00",
      "totalMessages": 3,
      "messageStats": {
        "total": 3,
        "sent": 2,
        "delivered": 2,
        "failed": 1,
        "pending": 0,
        "queued": 0,
        "cancelled": 0
      },
      "engagementRates": {
        "delivery_rate": 66.67,
        "open_rate": 50.0,
        "click_rate": 25.0,
        "click_through_rate": 12.5
      },
      "latestMessageDate": "2025-09-19T18:45:22+00:00"
    }
  ],
  "hydra:totalItems": 127,
  "hydra:view": {
    "@id": "/api/notification-tracker/notifications?page=1&itemsPerPage=5",
    "@type": "hydra:PartialCollectionView",
    "hydra:first": "/api/notification-tracker/notifications?page=1&itemsPerPage=5",
    "hydra:last": "/api/notification-tracker/notifications?page=26&itemsPerPage=5",
    "hydra:next": "/api/notification-tracker/notifications?page=2&itemsPerPage=5"
  }
}
```

**Improvements:**
- ✅ Rich pagination with proper Hydra controls
- ✅ Comprehensive message statistics  
- ✅ Real-time engagement metrics
- ✅ Detailed notification information
- ✅ Proper API Platform structure

---

### 📧 Enhanced Message API Response

**GET `/notification-tracker/messages?page=1&itemsPerPage=3`**
```json
{
  "@context": "/api/contexts/Message",
  "@id": "/api/notification-tracker/messages",
  "@type": "hydra:Collection",
  "hydra:member": [
    {
      "@id": "/api/notification-tracker/messages/01K5HFE70QHJNRP1YPE40C6X5F",
      "@type": "EmailMessage",
      "id": "01K5HFE70QHJNRP1YPE40C6X5F",
      "status": "sent",
      "transportName": "smtp",
      "createdAt": "2025-09-19T17:13:05+00:00",
      "sentAt": "2025-09-19T17:13:06+00:00",
      "retryCount": 0,
      "failureReason": null,
      "messageType": "email",
      "recipientCount": 1,
      "primaryRecipient": "user@example.com",
      "shortSubject": "Testing transport",
      "engagementStats": {
        "total_recipients": 1,
        "opened": 1,
        "clicked": 0,
        "bounced": 0,
        "total_opens": 3,
        "total_clicks": 0
      },
      "latestEvent": {
        "type": "opened",
        "occurred_at": "2025-09-19T17:15:22+00:00",
        "metadata": {
          "user_agent": "Mozilla/5.0...",
          "ip_address": "192.168.1.100"
        }
      },
      "notificationSummary": {
        "id": "01K5HHVWSFNF660WQPHGYFNTTC",
        "type": "auto_generated",
        "subject": "[Email] Testing transport",
        "importance": "normal"
      }
    }
  ],
  "hydra:totalItems": 89,
  "hydra:view": {
    "@id": "/api/notification-tracker/messages?page=1&itemsPerPage=3",
    "@type": "hydra:PartialCollectionView",
    "hydra:first": "/api/notification-tracker/messages?page=1&itemsPerPage=3",
    "hydra:last": "/api/notification-tracker/messages?page=30&itemsPerPage=3",
    "hydra:next": "/api/notification-tracker/messages?page=2&itemsPerPage=3"
  }
}
```

---

## 🔧 Advanced Filtering Examples

### Filter by Notification Type
```bash
GET /notification-tracker/notifications?type=auto_generated&importance=high
```

### Filter Messages by Status and Date Range  
```bash
GET /notification-tracker/messages?status=sent&createdAt[after]=2025-09-19&order[sentAt]=desc
```

### Search Notifications by Subject
```bash
GET /notification-tracker/notifications?subject=welcome&order[createdAt]=desc
```

### Filter Messages by Notification Type
```bash
GET /notification-tracker/messages?notification.type=marketing_campaign&order[engagementStats.opened]=desc
```

---

## 📊 Pagination Controls

### Flexible Pagination
- **Default**: 20 notifications per page, 25 messages per page
- **Maximum**: 100 items per page
- **Customizable**: `?itemsPerPage=50`
- **Navigation**: Proper first/last/next/prev links

### Pagination Examples
```bash
# Get first 10 notifications
GET /notification-tracker/notifications?itemsPerPage=10&page=1

# Get next page with 50 messages
GET /notification-tracker/messages?itemsPerPage=50&page=2

# Get maximum items per page
GET /notification-tracker/notifications?itemsPerPage=100
```

---

## 🎯 Key Benefits of Enhanced API

### 1. **Rich Data Context**
- Comprehensive statistics included in list views
- Real-time engagement metrics
- Detailed relationship information

### 2. **Enterprise-Grade Pagination**
- Proper API Platform Hydra pagination
- Configurable page sizes
- Efficient partial pagination

### 3. **Advanced Filtering**  
- Filter by multiple properties
- Date range filtering
- Cross-entity filtering (message by notification type)
- Text search capabilities

### 4. **Performance Optimized**
- Separate serialization groups for list vs detail views
- Computed properties for statistics
- Efficient database queries

### 5. **Developer Experience**
- Consistent route structure
- Predictable response format
- Comprehensive documentation
- Type-safe responses

---

## 🔗 Route Consistency

All endpoints now follow the centralized `/notification-tracker/` prefix:

| Resource | Endpoint Pattern |
|----------|------------------|
| Notifications | `/notification-tracker/notifications` |
| Messages | `/notification-tracker/messages` |
| Templates | `/notification-tracker/templates` |
| Events | `/notification-tracker/messages/{id}/events` |

### Centralized Configuration
```php
use Nkamuo\NotificationTrackerBundle\Config\ApiRoutes;

// All routes use centralized configuration
ApiRoutes::getNotification()     // /notification-tracker/notifications
ApiRoutes::getMessage()          // /notification-tracker/messages
ApiRoutes::getTemplate()         // /notification-tracker/templates
```

The API now provides enterprise-grade functionality with comprehensive data, intelligent pagination, and consistent routing! 🚀
