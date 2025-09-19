# Transport Decorators for Automatic Tracking

## Email Transport Decorator

```php
<?php
// src/Mailer/Transport/TrackingTransportDecorator.php

namespace App\Mailer\Transport;

use App\Service\Communication\MessageTracker;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class TrackingTransportDecorator implements TransportInterface
{
    public function __construct(
        private TransportInterface $decoratedTransport,
        private MessageTracker $messageTracker,
        private string $transportName
    ) {}

    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        // Track the email before sending
        if ($message instanceof Email) {
            $trackedMessage = $this->messageTracker->trackEmail(
                $message,
                $this->transportName,
                null,
                [
                    'envelope' => [
                        'sender' => $envelope?->getSender()->toString(),
                        'recipients' => array_map(
                            fn($r) => $r->toString(),
                            $envelope?->getRecipients() ?? []
                        ),
                    ],
                ]
            );

            // Add tracking headers
            $message->getHeaders()->addTextHeader('X-Message-ID', $trackedMessage->getId());
        }

        try {
            // Send via decorated transport
            $sentMessage = $this->decoratedTransport->send($message, $envelope);

            // Update tracking with provider message ID
            if ($sentMessage && $message instanceof Email) {
                $providerMessageId = $sentMessage->getMessageId();
                $trackedMessage->setMessageId($providerMessageId);
                
                $this->messageTracker->addEvent(
                    $trackedMessage,
                    MessageEvent::TYPE_SENT,
                    [
                        'provider_message_id' => $providerMessageId,
                        'debug' => $sentMessage->getDebug(),
                    ]
                );
            }

            return $sentMessage;
            
        } catch (\Exception $e) {
            // Track failure
            if (isset($trackedMessage)) {
                $this->messageTracker->addEvent(
                    $trackedMessage,
                    MessageEvent::TYPE_FAILED,
                    [
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                    ]
                );
            }

            throw $e;
        }
    }

    public function __toString(): string
    {
        return 'tracking://' . $this->decoratedTransport->__toString();
    }
}
```

## Transport Factory

```php
<?php
// src/Mailer/Transport/TrackingTransportFactory.php

namespace App\Mailer\Transport;

use App\Service\Communication\MessageTracker;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

class TrackingTransportFactory extends AbstractTransportFactory
{
    public function __construct(
        private MessageTracker $messageTracker,
        private TransportInterface $baseTransport
    ) {
        parent::__construct();
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $transportName = $dsn->getHost();
        
        return new TrackingTransportDecorator(
            $this->baseTransport,
            $this->messageTracker,
            $transportName
        );
    }

    protected function getSupportedSchemes(): array
    {
        return ['tracking'];
    }
}
```

## Notifier Channel Decorator

```php
<?php
// src/Notifier/Channel/TrackingChannelDecorator.php

namespace App\Notifier\Channel;

use App\Entity\Communication\Notification;
use App\Service\Communication\MessageTracker;
use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\Notifier\Notification\Notification as SymfonyNotification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

class TrackingChannelDecorator implements ChannelInterface
{
    public function __construct(
        private ChannelInterface $decoratedChannel,
        private MessageTracker $messageTracker,
        private string $channelType
    ) {}

    public function notify(SymfonyNotification $notification, RecipientInterface $recipient, string $transportName = null): void
    {
        // Create notification entity if not exists
        $trackedNotification = $this->findOrCreateNotification($notification, $recipient);

        // Track the message for this channel
        $message = $this->createMessageForChannel($notification, $recipient, $trackedNotification);

        try {
            // Send via decorated channel
            $this->decoratedChannel->notify($notification, $recipient, $transportName);

            // Mark as sent
            $this->messageTracker->addEvent(
                $message,
                MessageEvent::TYPE_SENT,
                ['channel' => $this->channelType]
            );
            
        } catch (\Exception $e) {
            // Track failure
            $this->messageTracker->addEvent(
                $message,
                MessageEvent::TYPE_FAILED,
                [
                    'channel' => $this->channelType,
                    'error' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    public function supports(SymfonyNotification $notification, RecipientInterface $recipient): bool
    {
        return $this->decoratedChannel->supports($notification, $recipient);
    }

    private function findOrCreateNotification(SymfonyNotification $notification, RecipientInterface $recipient): Notification
    {
        // Implementation to create/find notification entity
        // This would need custom logic based on your notification structure
    }

    private function createMessageForChannel(
        SymfonyNotification $notification,
        RecipientInterface $recipient,
        Notification $trackedNotification
    ): Message {
        // Create appropriate message type based on channel
        // EmailMessage, SmsMessage, SlackMessage, etc.
    }
}
```