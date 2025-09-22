# 🚀 Testing Your Notification Transport

## The Issue: `mailer:test` vs Message Bus

Your configuration is **100% correct**, but `mailer:test` doesn't use Messenger at all. Here's what happens:

### Current Flow (mailer:test):
```
mailer:test → MailerInterface → Transport (SendGrid/Mailgun) → EventSubscriber tracks it
```

### Desired Flow (message bus):
```
MessageBus → NotificationTransport → Queue → Consumer → MailerInterface → Transport → EventSubscriber tracks it
```

## ✅ Solution: Test with Message Bus

Create this test command in your application:

### 1. Create Test Command

```php
<?php
// src/Command/TestNotificationTransportCommand.php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mime\Email;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationCampaignStamp;

#[AsCommand(
    name: 'app:test-notification-transport',
    description: 'Test the notification transport with message bus'
)]
class TestNotificationTransportCommand extends Command
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Testing Notification Transport');
        
        // Create test email
        $email = (new Email())
            ->from('test@yourdomain.com')
            ->to('callistus@anvila.tech')
            ->subject('Transport Test - ' . date('H:i:s'))
            ->text('This email was sent through the notification transport!')
            ->html('<p>This email was sent through the <strong>notification transport</strong>!</p>');
        
        $io->info('Created email: ' . $email->getSubject());
        
        // Add stamps for tracking
        $stamps = [
            new NotificationProviderStamp('email', 10),
            new NotificationCampaignStamp('transport-test')
        ];
        
        $io->info('Adding tracking stamps...');
        
        // Dispatch through message bus (this will use your routing)
        $this->messageBus->dispatch(new SendEmailMessage($email), $stamps);
        
        $io->success('Email dispatched through notification transport!');
        $io->note([
            'The email is now queued in the notification transport.',
            'Check the queue: curl http://localhost:8001/api/notification-tracker/queue/messages',
            'Start consumer: php bin/console messenger:consume notification -vv'
        ]);
        
        return Command::SUCCESS;
    }
}
```

### 2. Test the Transport

```bash
# 1. Dispatch email through transport
php bin/console app:test-notification-transport

# 2. Check the queue (should now have messages!)
curl http://localhost:8001/api/notification-tracker/queue/messages

# 3. Start the consumer to process the queue
php bin/console messenger:consume notification -vv
```

## 🔍 Environment Variable Check

Make sure you have this in your `.env`:

```bash
# Your notification transport DSN
MESSENGER_TRANSPORT_NOTIFICATION_DSN="notification-tracking://doctrine?transport_name=email&analytics_enabled=true"
```

## ✅ Expected Results

After running the test command:

1. **Queue will have messages:**
   ```bash
   curl http://localhost:8001/api/notification-tracker/queue/messages
   # Should return actual queued messages
   ```

2. **Consumer will process them:**
   ```bash
   php bin/console messenger:consume notification -vv
   # Will show messages being processed through the transport
   ```

3. **Full tracking chain:**
   - Message queued → Transport processes → Mailer sends → EventSubscriber tracks

## 🎯 Why This Works

- `mailer:test` = Direct mailer (bypasses routing)
- `MessageBus::dispatch()` = Uses your routing configuration
- Your routing sends `SendEmailMessage` to `notification` transport
- The transport queues it for async processing
- Consumer processes the queue and sends the actual email
- EventSubscriber still tracks everything

## Summary

Your configuration is perfect! The issue was just that `mailer:test` doesn't use Messenger routing. Use the message bus directly and you'll see the transport working exactly as expected! 🚀
