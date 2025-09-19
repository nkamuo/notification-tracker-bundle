# Service Layer Implementation

## Message Tracker Service

```php
<?php
// src/Service/Communication/MessageTracker.php

namespace App\Service\Communication;

use App\Entity\Communication\EmailMessage;
use App\Entity\Communication\Message;
use App\Entity\Communication\MessageEvent;
use App\Entity\Communication\MessageRecipient;
use App\Entity\Communication\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent as MailerMessageEvent;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MessageTracker
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private MessageAnalytics $analytics
    ) {}

    /**
     * Track an outgoing email
     */
    public function trackEmail(
        Email $email,
        ?string $transportName = null,
        ?Notification $notification = null,
        array $metadata = []
    ): EmailMessage {
        $message = new EmailMessage();
        $message->setSubject($email->getSubject() ?? '');
        $message->setFromEmail($email->getFrom()[0]->getAddress());
        $message->setFromName($email->getFrom()[0]->getName());
        
        if ($email->getReplyTo()) {
            $message->setReplyTo($email->getReplyTo()[0]->getAddress());
        }

        $message->setTransportName($transportName);
        $message->setNotification($notification);
        $message->setMetadata($metadata);
        
        // Add recipients
        foreach ($email->getTo() as $address) {
            $recipient = new MessageRecipient();
            $recipient->setType(MessageRecipient::TYPE_TO);
            $recipient->setAddress($address->getAddress());
            $recipient->setName($address->getName());
            $message->addRecipient($recipient);
        }
        
        foreach ($email->getCc() as $address) {
            $recipient = new MessageRecipient();
            $recipient->setType(MessageRecipient::TYPE_CC);
            $recipient->setAddress($address->getAddress());
            $recipient->setName($address->getName());
            $message->addRecipient($recipient);
        }
        
        foreach ($email->getBcc() as $address) {
            $recipient = new MessageRecipient();
            $recipient->setType(MessageRecipient::TYPE_BCC);
            $recipient->setAddress($address->getAddress());
            $recipient->setName($address->getName());
            $message->addRecipient($recipient);
        }

        // Create content
        $content = new MessageContent();
        $content->setContentType($email->getHtmlBody() ? 'text/html' : 'text/plain');
        $content->setBodyText($email->getTextBody());
        $content->setBodyHtml($email->getHtmlBody());
        $message->setContent($content);

        // Add initial event
        $this->addEvent($message, MessageEvent::TYPE_QUEUED, [
            'transport' => $transportName,
        ]);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->logger->info('Email tracked', [
            'message_id' => $message->getId(),
            'subject' => $message->getSubject(),
            'recipients_count' => count($message->getRecipients()),
        ]);

        return $message;
    }

    /**
     * Record a message event
     */
    public function addEvent(
        Message $message,
        string $eventType,
        array $eventData = [],
        ?MessageRecipient $recipient = null,
        ?WebhookPayload $webhookPayload = null
    ): MessageEvent {
        $event = new MessageEvent();
        $event->setMessage($message);
        $event->setEventType($eventType);
        $event->setEventData($eventData);
        $event->setRecipient($recipient);
        $event->setWebhookPayload($webhookPayload);

        if (isset($eventData['ip_address'])) {
            $event->setIpAddress($eventData['ip_address']);
        }

        if (isset($eventData['user_agent'])) {
            $event->setUserAgent($eventData['user_agent']);
        }

        $message->addEvent($event);

        // Update message status based on event
        $this->updateMessageStatus($message, $eventType);

        // Update recipient status if applicable
        if ($recipient) {
            $this->updateRecipientStatus($recipient, $eventType);
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        // Dispatch domain event
        $this->eventDispatcher->dispatch(
            new MessageEventOccurred($message, $event),
            MessageEventOccurred::NAME
        );

        return $event;
    }

    /**
     * Update message status based on event type
     */
    private function updateMessageStatus(Message $message, string $eventType): void
    {
        $statusMap = [
            MessageEvent::TYPE_QUEUED => Message::STATUS_QUEUED,
            MessageEvent::TYPE_SENT => Message::STATUS_SENT,
            MessageEvent::TYPE_DELIVERED => Message::STATUS_DELIVERED,
            MessageEvent::TYPE_BOUNCED => Message::STATUS_BOUNCED,
            MessageEvent::TYPE_FAILED => Message::STATUS_FAILED,
        ];

        if (isset($statusMap[$eventType])) {
            $message->setStatus($statusMap[$eventType]);
            
            if ($eventType === MessageEvent::TYPE_SENT) {
                $message->setSentAt(new \DateTimeImmutable());
            }
        }

        $message->setUpdatedAt(new \DateTimeImmutable());
    }

    /**
     * Update recipient status based on event type
     */
    private function updateRecipientStatus(MessageRecipient $recipient, string $eventType): void
    {
        $statusMap = [
            MessageEvent::TYPE_SENT => MessageRecipient::STATUS_SENT,
            MessageEvent::TYPE_DELIVERED => MessageRecipient::STATUS_DELIVERED,
            MessageEvent::TYPE_OPENED => MessageRecipient::STATUS_OPENED,
            MessageEvent::TYPE_CLICKED => MessageRecipient::STATUS_CLICKED,
            MessageEvent::TYPE_BOUNCED => MessageRecipient::STATUS_BOUNCED,
            MessageEvent::TYPE_COMPLAINED => MessageRecipient::STATUS_COMPLAINED,
            MessageEvent::TYPE_UNSUBSCRIBED => MessageRecipient::STATUS_UNSUBSCRIBED,
        ];

        if (isset($statusMap[$eventType])) {
            $recipient->setStatus($statusMap[$eventType]);
            
            $timestampMap = [
                MessageEvent::TYPE_DELIVERED => 'setDeliveredAt',
                MessageEvent::TYPE_OPENED => 'setOpenedAt',
                MessageEvent::TYPE_CLICKED => 'setClickedAt',
                MessageEvent::TYPE_BOUNCED => 'setBouncedAt',
            ];

            if (isset($timestampMap[$eventType])) {
                $method = $timestampMap[$eventType];
                $recipient->$method(new \DateTimeImmutable());
            }
        }
    }

    /**
     * Find message by provider's message ID
     */
    public function findByProviderMessageId(string $providerMessageId, string $provider): ?Message
    {
        return $this->entityManager->getRepository(Message::class)
            ->findOneBy([
                'metadata' => ['provider_message_id' => $providerMessageId],
                'transportName' => $provider,
            ]);
    }
}
```

## Webhook Processor Service

```php
<?php
// src/Service/Communication/WebhookProcessor.php

namespace App\Service\Communication;

use App\Entity\Communication\MessageEvent;
use App\Entity\Communication\WebhookPayload;
use App\Service\Communication\Provider\ProviderWebhookInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class WebhookProcessor
{
    private iterable $providers;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageTracker $messageTracker,
        private LoggerInterface $logger,
        #[TaggedIterator('communication.webhook_provider')] iterable $providers
    ) {
        $this->providers = $providers;
    }

    /**
     * Process incoming webhook
     */
    public function processWebhook(
        string $provider,
        array $payload,
        array $headers = []
    ): WebhookPayload {
        $webhookPayload = new WebhookPayload();
        $webhookPayload->setProvider($provider);
        $webhookPayload->setRawPayload($payload);
        $webhookPayload->setSignature($headers['signature'] ?? null);

        $this->entityManager->persist($webhookPayload);

        try {
            $providerHandler = $this->getProviderHandler($provider);
            
            // Verify webhook signature
            if (!$providerHandler->verifySignature($payload, $headers)) {
                throw new \Exception('Invalid webhook signature');
            }

            // Parse webhook data
            $parsedData = $providerHandler->parseWebhook($payload);
            
            $webhookPayload->setEventType($parsedData['event_type']);

            // Find the message
            $message = $this->messageTracker->findByProviderMessageId(
                $parsedData['message_id'],
                $provider
            );

            if (!$message) {
                $this->logger->warning('Message not found for webhook', [
                    'provider' => $provider,
                    'message_id' => $parsedData['message_id'],
                ]);
                return $webhookPayload;
            }

            // Find recipient if applicable
            $recipient = null;
            if (isset($parsedData['recipient_email'])) {
                foreach ($message->getRecipients() as $r) {
                    if ($r->getAddress() === $parsedData['recipient_email']) {
                        $recipient = $r;
                        break;
                    }
                }
            }

            // Add event to message
            $this->messageTracker->addEvent(
                $message,
                $parsedData['event_type'],
                $parsedData['event_data'] ?? [],
                $recipient,
                $webhookPayload
            );

            $webhookPayload->setProcessed(true);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to process webhook', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            
            $webhookPayload->setProcessed(false);
        }

        $this->entityManager->flush();

        return $webhookPayload;
    }

    /**
     * Get provider webhook handler
     */
    private function getProviderHandler(string $provider): ProviderWebhookInterface
    {
        foreach ($this->providers as $handler) {
            if ($handler->supports($provider)) {
                return $handler;
            }
        }

        throw new \InvalidArgumentException("No webhook handler found for provider: $provider");
    }
}
```

## Provider Webhook Handlers

```php
<?php
// src/Service/Communication/Provider/SendGridWebhookHandler.php

namespace App\Service\Communication\Provider;

class SendGridWebhookHandler implements ProviderWebhookInterface
{
    public function __construct(
        private string $webhookSecret
    ) {}

    public function supports(string $provider): bool
    {
        return $provider === 'sendgrid';
    }

    public function verifySignature(array $payload, array $headers): bool
    {
        if (!isset($headers['X-Twilio-Email-Event-Webhook-Signature'])) {
            return false;
        }

        $signature = $headers['X-Twilio-Email-Event-Webhook-Signature'];
        $timestamp = $headers['X-Twilio-Email-Event-Webhook-Timestamp'] ?? '';
        
        $signedContent = $timestamp . json_encode($payload);
        $expectedSignature = base64_encode(
            hash_hmac('sha256', $signedContent, $this->webhookSecret, true)
        );

        return hash_equals($expectedSignature, $signature);
    }

    public function parseWebhook(array $payload): array
    {
        $eventMap = [
            'processed' => MessageEvent::TYPE_QUEUED,
            'dropped' => MessageEvent::TYPE_FAILED,
            'delivered' => MessageEvent::TYPE_DELIVERED,
            'deferred' => MessageEvent::TYPE_FAILED,
            'bounce' => MessageEvent::TYPE_BOUNCED,
            'open' => MessageEvent::TYPE_OPENED,
            'click' => MessageEvent::TYPE_CLICKED,
            'spamreport' => MessageEvent::TYPE_COMPLAINED,
            'unsubscribe' => MessageEvent::TYPE_UNSUBSCRIBED,
        ];

        $events = [];
        foreach ($payload as $event) {
            $eventType = $eventMap[$event['event']] ?? null;
            
            if (!$eventType) {
                continue;
            }

            $events[] = [
                'event_type' => $eventType,
                'message_id' => $event['sg_message_id'] ?? $event['smtp-id'],
                'recipient_email' => $event['email'],
                'event_data' => [
                    'timestamp' => $event['timestamp'],
                    'ip' => $event['ip'] ?? null,
                    'user_agent' => $event['useragent'] ?? null,
                    'url' => $event['url'] ?? null,
                    'reason' => $event['reason'] ?? null,
                ],
            ];
        }

        return count($events) === 1 ? $events[0] : ['events' => $events];
    }
}
```

```php
<?php
// src/Service/Communication/Provider/TwilioWebhookHandler.php

namespace App\Service\Communication\Provider;

class TwilioWebhookHandler implements ProviderWebhookInterface
{
    public function __construct(
        private string $authToken
    ) {}

    public function supports(string $provider): bool
    {
        return $provider === 'twilio';
    }

    public function verifySignature(array $payload, array $headers): bool
    {
        if (!isset($headers['X-Twilio-Signature'])) {
            return false;
        }

        $url = $headers['X-Twilio-Url'] ?? '';
        $signature = $headers['X-Twilio-Signature'];
        
        // Build the data string
        $data = $url;
        ksort($payload);
        foreach ($payload as $key => $value) {
            $data .= $key . $value;
        }

        $expectedSignature = base64_encode(
            hash_hmac('sha256', $data, $this->authToken, true)
        );

        return hash_equals($expectedSignature, $signature);
    }

    public function parseWebhook(array $payload): array
    {
        $statusMap = [
            'queued' => MessageEvent::TYPE_QUEUED,
            'sent' => MessageEvent::TYPE_SENT,
            'delivered' => MessageEvent::TYPE_DELIVERED,
            'undelivered' => MessageEvent::TYPE_BOUNCED,
            'failed' => MessageEvent::TYPE_FAILED,
        ];

        return [
            'event_type' => $statusMap[$payload['MessageStatus']] ?? MessageEvent::TYPE_FAILED,
            'message_id' => $payload['MessageSid'],
            'recipient_email' => $payload['To'],
            'event_data' => [
                'from' => $payload['From'] ?? null,
                'error_code' => $payload['ErrorCode'] ?? null,
                'error_message' => $payload['ErrorMessage'] ?? null,
            ],
        ];
    }
}
```