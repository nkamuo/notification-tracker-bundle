# Custom API Platform Filters

> **⚠️ EXPERIMENTAL FEATURES** - These filters are in active development and may change without notice. Do not use in production.

This document describes the custom API Platform filters implemented for the Notification Tracker Bundle.

## ⚠️ Important Notice

**These filters are experimental and should not be used in production:**

- 🧪 **Experimental Status**: APIs may change without deprecation warnings
- 🚧 **Under Development**: Features may not work as expected
- 📝 **Subject to Change**: Parameter names and behavior may be modified
- 🔬 **Testing Only**: Use only in development/testing environments

## NotInFilter

The `NotInFilter` allows you to exclude records where a property value matches any value in a given set.

### Usage

```
GET /api/notification-tracker/messages?status[notin]=pending,failed,cancelled
```

### Syntax

- **Parameter Format**: `{property}[notin]={value1,value2,value3}`
- **Supported Properties**: Any mapped entity property
- **Value Format**: Comma-separated values
- **Example**: `?status[notin]=pending,failed` excludes messages with status "pending" or "failed"

### Available Properties for Messages

- `status[notin]` - Exclude messages with specific statuses
- `type[notin]` - Exclude specific message types (email, sms, etc.)
- `transportName[notin]` - Exclude specific transport names
- `notification.status[notin]` - Exclude messages from notifications with specific statuses
- `notification.type[notin]` - Exclude messages from specific notification types
- `notification.direction[notin]` - Exclude messages from specific notification directions

### Examples

```bash
# Exclude pending and failed messages
GET /api/notification-tracker/messages?status[notin]=pending,failed

# Exclude draft and cancelled notifications' messages
GET /api/notification-tracker/messages?notification.status[notin]=draft,cancelled

# Exclude email and SMS messages (show only push, slack, etc.)
GET /api/notification-tracker/messages?type[notin]=email,sms

# Exclude messages from inbound and draft notifications
GET /api/notification-tracker/messages?notification.direction[notin]=inbound,draft

# Combine multiple filters
GET /api/notification-tracker/messages?status[notin]=failed,cancelled&type[notin]=sms
```

## NotEqualsFilter

The `NotEqualsFilter` allows you to exclude records where a property equals a specific value.

### Usage

```
GET /api/notification-tracker/messages?status[ne]=pending
```

### Syntax

- **Parameter Format**: `{property}[ne]={value}`
- **Supported Properties**: Any mapped entity property
- **Special Values**: Use `"null"` to exclude null values
- **Example**: `?status[ne]=pending` excludes messages with status "pending"

### Available Properties for Messages

- `status[ne]` - Exclude messages with a specific status
- `type[ne]` - Exclude a specific message type
- `transportName[ne]` - Exclude a specific transport name
- `subject[ne]` - Exclude messages with a specific subject
- `notification.status[ne]` - Exclude messages from notifications with a specific status
- `notification.type[ne]` - Exclude messages from a specific notification type
- `notification.direction[ne]` - Exclude messages from a specific notification direction
- `notification.subject[ne]` - Exclude messages from notifications with a specific subject

### Examples

```bash
# Exclude pending messages
GET /api/notification-tracker/messages?status[ne]=pending

# Exclude email messages
GET /api/notification-tracker/messages?type[ne]=email

# Exclude messages with null subjects
GET /api/notification-tracker/messages?subject[ne]=null

# Exclude messages from outbound notifications
GET /api/notification-tracker/messages?notification.direction[ne]=outbound

# Exclude messages from notifications with a specific type
GET /api/notification-tracker/messages?notification.type[ne]=welcome

# Combine with regular filters
GET /api/notification-tracker/messages?status=sent&type[ne]=sms&notification.direction[ne]=draft
```

## Practical Use Cases

### 1. Get Only Successful Messages (Exclude Failed States)

```bash
# Exclude all problematic statuses
GET /api/notification-tracker/messages?status[notin]=pending,failed,cancelled,bounced
```

### 2. Get All Non-Email Communications

```bash
# Show SMS, push, slack, etc. but not emails
GET /api/notification-tracker/messages?type[ne]=email
```

### 3. Get Messages Not in Draft State

```bash
# Exclude draft notifications
GET /api/notification-tracker/messages?notification.direction[ne]=draft
```

### 4. Complex Filtering

```bash
# Get sent messages that are not emails or SMS, from non-draft notifications
GET /api/notification-tracker/messages?status=sent&type[notin]=email,sms&notification.direction[ne]=draft
```

## Technical Details

### NotInFilter Implementation

- **Strategy**: Uses SQL `NOT IN` clause
- **Parameter**: `[notin]`
- **Value Processing**: Splits comma-separated values, trims whitespace, filters empty values
- **SQL Generation**: `WHERE field NOT IN (:param)`

### NotEqualsFilter Implementation

- **Strategy**: Uses SQL `!=` operator with NULL handling
- **Parameter**: `[ne]`
- **NULL Handling**: Special case for excluding null values using `IS NOT NULL`
- **SQL Generation**: `WHERE field != :param OR field IS NULL`

### Nested Property Support

Both filters support nested properties using dot notation:
- `notification.status` becomes `notification_status` in API parameter
- Automatically handles entity relationships
- Maintains proper SQL joins

### Error Handling

- Invalid properties are silently ignored
- Empty values are filtered out
- Non-array values for NotInFilter are ignored
- Missing strategy parameters return early without affecting query

## Performance Considerations

1. **Indexing**: Ensure filtered properties have database indexes
2. **Query Optimization**: Use specific filters before broad exclusions
3. **Pagination**: Combine with pagination for large result sets
4. **Caching**: Consider caching frequent filter combinations

## Validation

- Properties must be mapped in the entity
- Values are validated according to property types
- Enum properties automatically validate against enum values
- Invalid enum values return no results (filtered out by database)
