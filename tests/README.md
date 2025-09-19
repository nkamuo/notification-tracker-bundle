# NotificationTrackerBundle Tests

This directory contains tests for the NotificationTrackerBundle.

## Running Tests

### All Tests
```bash
composer test
```

### With Coverage
```bash
vendor/bin/phpunit --coverage-html coverage/
```

### Specific Test Files
```bash
vendor/bin/phpunit tests/Unit/Repository/MessageRepositoryTest.php
```

## Test Structure

- `Unit/` - Unit tests for individual classes
- `Integration/` - Integration tests for combined functionality
- `Functional/` - Functional tests for full feature workflows
- `Fixtures/` - Test data and fixtures

## Writing Tests

When adding new features, please include:

1. **Unit tests** for individual classes and methods
2. **Integration tests** for database interactions
3. **Functional tests** for API endpoints and user workflows

## Test Database

Tests use a separate SQLite database by default. Configuration is in `phpunit.xml.dist`.

## Mocking

We use PHPUnit's built-in mocking capabilities along with:
- Doctrine test helpers for repository testing
- Symfony test client for functional testing
