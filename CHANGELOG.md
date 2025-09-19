# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.4] - 2025-09-19

### Fixed
- Removed references to non-existent controller and service classes
- Commented out service definitions for classes not yet implemented
- Fixed RegisterControllerArgumentLocatorsPass error during installation
- Simplified service configuration to only include existing classes
- Improved YAML syntax and removed duplicate service definitions

### Changed
- Service configurations now only reference implemented classes
- Non-existent analytics, template, and additional command services are commented out
- Cleaner, more maintainable service definitions

## [0.1.3] - 2025-09-19

### Fixed
- Added missing configuration files: tracking.yaml, analytics.yaml, templates.yaml
- Added file existence checks in NotificationTrackerExtension to prevent FileLocator errors
- Resolves "The file 'tracking.yaml' does not exist" installation error
- Improved robustness of service container loading

### Added
- Complete service definitions for tracking, analytics, and template functionality
- Proper dependency injection configuration for all bundle services

## [0.1.2] - 2025-09-19

### Fixed
- Fixed undefined array key "channels" error in NotificationTrackerExtension
- Added missing configuration sections (channels, templates, api, analytics, messenger)
- Added safety checks for configuration array access to prevent installation errors

## [0.1.1] - 2025-09-19

### Changed
- Updated Doctrine ORM dependency to support both 2.11+ and 3.0+ versions for better compatibility

## [0.1.0] - 2025-09-19

### Added
- Initial pre-release of NotificationTrackerBundle
- Multi-channel notification tracking (Email, SMS, Slack, Telegram, Push)
- Native Symfony Mailer and Notifier integration
- Comprehensive webhook support for major providers (SendGrid, Twilio, Mailgun, Mailchimp)
- File attachment handling and tracking
- Template management system with multi-language support
- Async processing with Symfony Messenger
- API Platform integration for REST/GraphQL APIs
- Real-time analytics and reporting
- Configurable webhook signature verification
- IP whitelisting for webhooks
- Retry mechanism for failed messages
- Event-driven architecture with custom events
- Advanced recipient management
- Content versioning and structured data support
- Comprehensive repository layer with optimized queries
- Docker support for development
- Extensive documentation and examples

### Features
- Complete message lifecycle tracking
- Multi-format content support (text, HTML, structured data)
- Advanced search and filtering capabilities
- Statistical analysis and reporting
- Security features (signature verification, IP whitelisting)
- Configurable retry policies
- Template variable substitution
- Webhook endpoint management
