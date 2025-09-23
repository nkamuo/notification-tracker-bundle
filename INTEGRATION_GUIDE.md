# 🔧 Integration Guide: Getting Queue Data

## Current Status Analysis

Based on your configuration and test results, your notification tracker bundle is working perfectly as an **event subscriber**, but you're not using the **custom transport** feature that provides queue management.

### What's Working ✅
- Event tracking via `MailerEventSubscriber`
- Database storage of all email tracking data
- Failure and retry tracking
- API endpoints (but no queue data to show)

### What's Missing ❌
- Messages in the transport queue (because you're using direct mailer)
- Custom transport message dispatching

## Option 1: Use Custom Transport for Queue Management

### Step 1: Configure Messenger Transport

In your `config/packages/messenger.yaml`:

```yaml
framework:
    messenger:
        transports:
            # Add the notification transport
            notification:
                dsn: 'notification-tracking://doctrine?transport_name=email&analytics_enabled=true'
                
            # Your existing transports stay the same
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
            
        routing:
            # Route notification messages through our custom transport
            'Nkamuo\NotificationTrackerBundle\Message\*': notification
            
            # Route Symfony mailer messages through our transport for queue management
            'Symfony\Component\Mailer\Messenger\SendEmailMessage': notification
```

### Step 2: Dispatch Messages Through Messenger

Instead of using `$mailer->send()` directly, use the message bus:

```php
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;

class YourController
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}
    
    public function sendEmail(): Response
    {
        $email = (new Email())
            ->from('hello@example.com')
            ->to('user@example.com')
            ->subject('Test Email')
            ->text('Hello World!');
            
        // This will go through the notification transport and appear in queue
        $this->messageBus->dispatch(new SendEmailMessage($email));
        
        return new Response('Email queued!');
    }
}
```

### Step 3: Start Message Consumers

```bash
# Start the notification transport worker
php bin/console messenger:consume notification -vv
```

Now your queue endpoints will show data:
- `GET /api/notification-tracker/queue/messages` - Will show queued messages
- `GET /api/notification-tracker/queue/stats` - Will show queue statistics

## Option 2: Keep Current Setup + Add Queue Visualization

If you prefer to keep using direct mailer (as you currently do), you can create API endpoints that show your tracked messages as "queue-like" data.

### Create a Custom Queue Controller

```php
// src/Controller/Api/TrackedMessagesController.php
<?php

namespace App\Controller\Api;

use Nkamuo\NotificationTrackerBundle\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/tracked-messages')]
class TrackedMessagesController extends AbstractController
{
    public function __construct(
        private MessageRepository $messageRepository
    ) {}
    
    #[Route('', methods: ['GET'])]
    public function getMessages(): JsonResponse
    {
        $messages = $this->messageRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            20
        );
        
        $data = [];
        foreach ($messages as $message) {
            $data[] = [
                'id' => $message->getId()->toString(),
                'status' => $message->getStatus(),
                'type' => $message->getType(),
                'subject' => $message instanceof \Nkamuo\NotificationTrackerBundle\Entity\EmailMessage 
                    ? $message->getSubject() : null,
                'createdAt' => $message->getCreatedAt()?->format('Y-m-d H:i:s'),
                'retryCount' => $message->getRetryCount(),
                'failureReason' => $message->getFailureReason(),
            ];
        }
        
        return $this->json($data);
    }
    
    #[Route('/stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        $stats = [
            'total' => $this->messageRepository->count([]),
            'pending' => $this->messageRepository->count(['status' => 'pending']),
            'queued' => $this->messageRepository->count(['status' => 'queued']),
            'sent' => $this->messageRepository->count(['status' => 'sent']),
            'failed' => $this->messageRepository->count(['status' => 'failed']),
        ];
        
        return $this->json($stats);
    }
}
```

### Access Your Tracked Messages

```bash
# Get recent tracked messages
curl http://localhost:8001/api/tracked-messages

# Get message statistics
curl http://localhost:8001/api/tracked-messages/stats
```

## Recommendation

**I recommend Option 1** because:
1. You get full queue management capabilities
2. Better retry handling and message processing control
3. True asynchronous message processing
4. All the existing tracking still works
5. Better scalability for high-volume scenarios

Your current event subscriber approach will continue working alongside the transport, giving you the best of both worlds!

## Testing the Integration

After implementing Option 1:

1. **Send a test email through messenger:**
   ```bash
   # Create a test command that uses the message bus
   php bin/console app:send-test-email user@example.com
   ```

2. **Check the queue:**
   ```bash
   curl http://localhost:8001/api/notification-tracker/queue/messages
   ```

3. **Start the consumer:**
   ```bash
   php bin/console messenger:consume notification
   ```

4. **Watch the messages process and appear in your tracking database!**
