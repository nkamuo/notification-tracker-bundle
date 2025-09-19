# Check that the notification tracker routes are loaded

# 1. List all routes
php bin/console debug:router

# 2. Filter for notification tracker routes
php bin/console debug:router | grep notification

# 3. Check API Platform routes specifically
php bin/console api:openapi:export --yaml

# 4. Test the API endpoints
curl http://localhost:8000/api/notification-tracker/messages

# The routes you should see:
# GET    /api/notification-tracker/messages           - List all messages
# GET    /api/notification-tracker/messages/{id}      - Get single message  
# POST   /api/notification-tracker/messages/{id}/retry - Retry message
# POST   /api/notification-tracker/messages/{id}/cancel - Cancel message
# DELETE /api/notification-tracker/messages/{id}      - Delete message
