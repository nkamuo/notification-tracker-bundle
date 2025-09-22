# NotificationTrackerBundle Releases

## v0.10.2 (2025-09-22) - Current Release ✅

### 🐛 Critical Fixes
- **Added missing NotificationSender implementation** - Complete service implementation that was missing
- **Fixed webhook provider service configuration** - Resolved dependency injection errors
- **Updated CHANGELOG.md** - Added proper release documentation for v0.10.0, v0.10.1, v0.10.2
- **Cleaned up documentation structure** - Moved scattered docs to organized folders

### 🔧 Technical Improvements
- Complete NotificationSender with Messenger DelayStamp integration
- Fixed WebhookProviderRegistry dependency injection
- Proper webhook provider service configuration with optional secrets
- Enhanced compiler pass for webhook provider collection

### 📦 Installation
```bash
composer require nkamuo/notification-tracker-bundle:^0.10.2
```

## v0.10.1 (2025-09-22)

### 🐛 Bug Fixes
- Fixed webhook provider constructor parameter mismatches
- Resolved service container dependency injection errors

## v0.10.0 (2025-09-22)

### 🚀 Major Features
- **Symfony Messenger Integration** - Complete DelayStamp-based scheduling
- **Individual Message Scheduling** - Millisecond precision with DelayStamp  
- **Enhanced Status Tracking** - New convenience methods for notifications
- **Unified API** - Streamlined notification sending through MessageBus

### ⚠️ Breaking Changes
- Removed deprecated draft-based controllers and commands
- Replaced NotificationDraft entity with enhanced Notification scheduling
- Updated API endpoints to unified notification scheduling

---

## Migration Guide from v0.9.x to v0.10.x

### 1. Update Composer
```bash
composer require nkamuo/notification-tracker-bundle:^0.10.2
```

### 2. Update Code
- Replace any usage of `NotificationDraft` with `Notification` entities
- Update scheduling logic to use the new unified API
- Remove references to deprecated draft controllers

### 3. Database Migration
- Run database migrations to remove NotificationDraft table
- Update any custom code referencing draft entities

### 4. Configuration
- Update messenger configuration if using custom transport
- Configure webhook secrets in environment variables (optional)

---

## Documentation

- **Installation Guide**: [docs/installation/](docs/installation/)
- **API Documentation**: [docs/api-examples/](docs/api-examples/)
- **Notification Tracking**: [docs/notification-tracking/](docs/notification-tracking/)
- **Integration Guides**: [docs/guides/](docs/guides/)

---

## Support

- **Issues**: [GitHub Issues](https://github.com/nkamuo/notification-tracker-bundle/issues)
- **Documentation**: [docs/](docs/)
- **Changelog**: [CHANGELOG.md](CHANGELOG.md)
