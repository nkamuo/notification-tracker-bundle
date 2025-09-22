# Label Notification Counting

The Label entity now includes automatic notification counting functionality that tracks how many notifications are associated with each label.

## Features

### Automatic Count Management
- **Auto-increment**: When a notification is added to a label, the count increases automatically
- **Auto-decrement**: When a notification is removed from a label, the count decreases automatically  
- **Consistency checks**: PostLoad lifecycle callback ensures counts stay in sync with actual data

### API Filtering and Searching
Labels can now be filtered by their notification count using range filters:

```http
# Get labels with exactly 5 notifications
GET /api/labels?notificationCount=5

# Get labels with more than 10 notifications  
GET /api/labels?notificationCount[gt]=10

# Get labels with between 5 and 20 notifications
GET /api/labels?notificationCount[gte]=5&notificationCount[lte]=20

# Get labels with no notifications
GET /api/labels?notificationCount=0

# Order by notification count (most used labels first)
GET /api/labels?order[notificationCount]=desc
```

### Available Range Filter Operations
- `notificationCount[gt]=N` - Greater than N
- `notificationCount[gte]=N` - Greater than or equal to N
- `notificationCount[lt]=N` - Less than N
- `notificationCount[lte]=N` - Less than or equal to N
- `notificationCount[between]=N..M` - Between N and M

## Database Schema

The `notification_count` field is stored as an integer with:
- **Default value**: 0
- **Database index**: For optimal query performance on count-based filters
- **Not null constraint**: Ensures data integrity

## Synchronization Command

If label counts ever get out of sync with actual data, use the sync command:

```bash
php bin/console notification-tracker:sync-label-counts
```

This command will:
1. Check all labels for count discrepancies
2. Update counts to match actual notification relationships
3. Provide verbose output showing what was corrected
4. Display summary of changes made

## API Response Format

Labels now include the notification count in their JSON representation:

```json
{
    "id": "01HKQJ7Z8W3X9Y2V1R5F4N6K8P",
    "name": "marketing",
    "color": "#007bff", 
    "description": "Marketing campaign notifications",
    "notificationCount": 15,
    "createdAt": "2024-01-15T10:30:00+00:00",
    "updatedAt": "2024-01-20T14:22:00+00:00"
}
```

## Performance Considerations

### Indexing
- The `notification_count` field is indexed for fast range queries
- Filtering by count is highly optimized for large datasets

### Count Maintenance
- Counts are updated in real-time during notification-label operations
- No expensive COUNT() queries needed for displaying label statistics
- PostLoad callback provides automatic consistency checking

## Example Use Cases

### Dashboard Analytics
```http
# Get most popular labels for dashboard
GET /api/labels?order[notificationCount]=desc&limit=10
```

### Label Management
```http
# Find unused labels for cleanup
GET /api/labels?notificationCount=0

# Find heavily used labels that might need review
GET /api/labels?notificationCount[gt]=100
```

### Reporting
```http
# Get labels with moderate usage for analysis
GET /api/labels?notificationCount[gte]=10&notificationCount[lte]=50
```

## Migration Notes

For existing installations:
1. The migration automatically adds the `notification_count` column
2. Existing labels get their counts calculated and populated
3. The database index is created automatically
4. No manual intervention required

The migration handles:
- Adding the new column with appropriate defaults
- Creating the performance index
- Populating existing data with correct counts
- Ensuring referential integrity
