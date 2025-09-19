# Symfony Native Event Integration

## Overview
This document details the integration with Symfony's native Mailer and Notifier events to automatically track message lifecycle without requiring transport decorators.

## Symfony Mailer Events

Symfony Mailer dispatches these events:
- `Symfony\Component\Mailer\Event\MessageEvent` - Before sending
- `Symfony\Component\Mailer\Event\SentMessageEvent` - After successful send
- `Symfony\Component\Mailer\Event\FailedMessageEvent` - On send failure

## Symfony Notifier Events

Symfony Notifier dispatches these events:
- `Symfony\Component\Notifier\Event\MessageEvent` - Before sending notification
- `Symfony\Component\Notifier\Event\SentMessageEvent` - After successful send
- `Symfony\Component\Notifier\Event\FailedMessageEvent` - On send failure

## Event Subscribers

```php
<?php
// src/EventSubscriber/MailerEventSubscriber.php

namespace App\EventSubscriber;

use App\Entity\Communication\EmailMessage;
use App\Entity\Communication\MessageEvent as TrackedMessageEvent;
use App\Service\Communication\MessageTracker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Uid\Uuid;

class MailerEventSubscriber implements EventSubscriberInterface
{
    private array $messageMap = [];

    public function __construct(
        private MessageTracker $messageTracker,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => ['onMessage', 100],
            SentMessageEvent::class => ['onSentMessage', 0],
            FailedMessageEvent::class => ['onFailedMessage', 0],
        ];
    }

    /**
     * Handle pre-send event - create tracking entity
     */
    public function onMessage(MessageEvent $event): void
    {
        $message = $event->getMessage();
        
        if (!$message instanceof Email) {
            return;
        }

        try {
            // Check if already tracked (in case of retries)
            $trackingId = $this->getTrackingId($message);
            if ($trackingId && $this->findTrackedMessage($trackingId)) {
                return;
            }

            // Extract notification context if present
            $notification = $this->extractNotificationFromHeaders($message->getHeaders());

            // Create tracking entity
            $trackedMessage = $this->messageTracker->trackEmail(
                $message,
                $this->extractTransportName($event),
                $notification,
                [
                    'queued' => $event->isQueued(),
                    'symfony_event' => 'MessageEvent',
                ]
            );

            // Store tracking ID in message headers
            $message->getHeaders()->addTextHeader('X-Tracking-ID', (string) $trackedMessage->getId());

            // Map Symfony message to our tracked message for later reference
            $messageId = spl_object_id($message);
            $this->messageMap[$messageId] = $trackedMessage;

            $this->logger->info('Email tracked via Symfony MessageEvent', [
                'tracking_id' => $trackedMessage->getId(),
                'subject' => $message->getSubject(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to track email in MessageEvent', [
                'error' => $e->getMessage(),
                'subject' => $message->getSubject(),
            ]);
        }
    }

    /**
     * Handle successful send event
     */
    public function onSentMessage(SentMessageEvent $event): void
    {
        $message = $event->getMessage()->getOriginalMessage();
        
        if (!$message instanceof Email) {
            return;
        }

        try {
            $trackedMessage = $this->getTrackedMessage($message);
            
            if (!$trackedMessage) {
                $this->logger->warning('No tracked message found for SentMessageEvent', [
                    'subject' => $message->getSubject(),
                ]);
                return;
            }

            // Get provider message ID from sent message
            $sentMessage = $event->getMessage();
            $providerMessageId = $sentMessage->getMessageId();
            
            // Update message with provider ID
            if ($trackedMessage instanceof EmailMessage) {
                $trackedMessage->setMessageId($providerMessageId);
            }

            // Add sent event
            $this->messageTracker->addEvent(
                $trackedMessage,
                TrackedMessageEvent::TYPE_SENT,
                [
                    'provider_message_id' => $providerMessageId,
                    'debug' => $sentMessage->getDebug(),
                    'symfony_event' => 'SentMessageEvent',
                    'transport' => $sentMessage->getEnvelope()?->getSender()?->toString(),
                ]
            );

            // Store provider message ID mapping for webhook lookup
            $this->storeProviderMapping($trackedMessage, $providerMessageId);

            $this->logger->info('Email sent event tracked', [
                'tracking_id' => $trackedMessage->getId(),
                'provider_message_id' => $providerMessageId,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to track sent email event', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            // Clean up memory
            $this->cleanupMessageMap($message);
        }
    }

    /**
     * Handle failed send event
     */
    public function onFailedMessage(FailedMessageEvent $event): void
    {
        $message = $event->getMessage();
        
        if (!$message instanceof Email) {
            return;
        }

        try {
            $trackedMessage = $this->getTrackedMessage($message);
            
            if (!$trackedMessage) {
                // Create a new tracking entry for failed message
                $trackedMessage = $this->messageTracker->trackEmail(
                    $message,
                    null,
                    null,
                    ['symfony_event' => 'FailedMessageEvent']
                );
            }

            // Add failed event
            $error = $event->getError();
            $this->messageTracker->addEvent(
                $trackedMessage,
                TrackedMessageEvent::TYPE_FAILED,
                [
                    'error_message' => $error?->getMessage(),
                    'error_class' => $error ? get_class($error) : null,
                    'error_code' => $error?->getCode(),
                    'symfony_event' => 'FailedMessageEvent',
                ]
            );

            // Update failure reason
            $trackedMessage->setFailureReason($error?->getMessage());
            $trackedMessage->incrementRetryCount();
            
            $this->entityManager->flush();

            $this->logger->error('Email failed event tracked', [
                'tracking_id' => $trackedMessage->getId(),
                'error' => $error?->getMessage(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to track failed email event', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            // Clean up memory
            $this->cleanupMessageMap($message);
        }
    }

    /**
     * Get tracked message from Symfony message
     */
    private function getTrackedMessage(Email $message): ?EmailMessage
    {
        // First try to get from memory map
        $messageId = spl_object_id($message);
        if (isset($this->messageMap[$messageId])) {
            return $this->messageMap[$messageId];
        }

        // Then try to get from headers
        $trackingId = $this->getTrackingId($message);
        if ($trackingId) {
            return $this->findTrackedMessage($trackingId);
        }

        return null;
    }

    /**
     * Get tracking ID from message headers
     */
    private function getTrackingId(Email $message): ?string
    {
        $header = $message->getHeaders()->get('X-Tracking-ID');
        return $header?->getBody();
    }

    /**
     * Find tracked message by ID
     */
    private function findTrackedMessage(string $trackingId): ?EmailMessage
    {
        try {
            $uuid = Uuid::fromString($trackingId);
            return $this->entityManager->getRepository(EmailMessage::class)->find($uuid);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract transport name from event
     */
    private function extractTransportName(MessageEvent $event): ?string
    {
        // This would need reflection or other mechanism to get transport name
        // from the event or envelope
        return null;
    }

    /**
     * Extract notification from headers if present
     */
    private function extractNotificationFromHeaders(Headers $headers): ?Notification
    {
        $notificationId = $headers->get('X-Notification-ID')?->getBody();
        
        if (!$notificationId) {
            return null;
        }

        try {
            $uuid = Uuid::fromString($notificationId);
            return $this->entityManager->getRepository(Notification::class)->find($uuid);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Store provider message ID mapping for webhook lookup
     */
    private function storeProviderMapping(EmailMessage $message, string $providerMessageId): void
    {
        $metadata = $message->getMetadata();
        $metadata['provider_message_id'] = $providerMessageId;
        $message->setMetadata($metadata);
        $this->entityManager->flush();
    }

    /**
     * Clean up memory map
     */
    private function cleanupMessageMap(Email $message): void
    {
        $messageId = spl_object_id($message);
        unset($this->messageMap[$messageId]);
    }
}
```

```php
<?php
// src/EventSubscriber/NotifierEventSubscriber.php

namespace App\EventSubscriber;

use App\Entity\Communication\Message;
use App\Entity\Communication\MessageEvent as TrackedMessageEvent;
use App\Entity\Communication\Notification;
use App\Entity\Communication\SmsMessage;
use App\Entity\Communication\SlackMessage;
use App\Entity\Communication\TelegramMessage;
use App\Service\Communication\MessageTracker;
use App\Service\Communication\NotificationTracker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Notifier\Event\FailedMessageEvent;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Event\SentMessageEvent;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage as NotifierSmsMessage;
use Symfony\Component\Notifier\Notification\Notification as SymfonyNotification;

class NotifierEventSubscriber implements EventSubscriberInterface
{
    private array $messageMap = [];
    private array $notificationMap = [];

    public function __construct(
        private MessageTracker $messageTracker,
        private NotificationTracker $notificationTracker,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => ['onMessage', 100],
            SentMessageEvent::class => ['onSentMessage', 0],
            FailedMessageEvent::class => ['onFailedMessage', 0],
        ];
    }

    /**
     * Handle pre-send notification event
     */
    public function onMessage(MessageEvent $event): void
    {
        $message = $event->getMessage();
        
        try {
            // Get or create notification entity
            $notification = $this->getOrCreateNotification($event);
            
            // Create appropriate tracked message based on type
            $trackedMessage = $this->createTrackedMessage($message, $notification);
            
            if (!$trackedMessage) {
                return;
            }

            // Map for later reference
            $messageId = spl_object_id($message);
            $this->messageMap[$messageId] = $trackedMessage;

            // Add queued event
            $this->messageTracker->addEvent(
                $trackedMessage,
                TrackedMessageEvent::TYPE_QUEUED,
                [
                    'channel' => $this->getChannelType($message),
                    'symfony_event' => 'NotifierMessageEvent',
                ]
            );

            $this->logger->info('Notification tracked via Symfony MessageEvent', [
                'tracking_id' => $trackedMessage->getId(),
                'type' => $trackedMessage->getType(),
                'notification_id' => $notification?->getId(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to track notification in MessageEvent', [
                'error' => $e->getMessage(),
                'message_type' => get_class($message),
            ]);
        }
    }

    /**
     * Handle successful notification send
     */
    public function onSentMessage(SentMessageEvent $event): void
    {
        $message = $event->getMessage();
        
        try {
            $trackedMessage = $this->getTrackedMessage($message);
            
            if (!$trackedMessage) {
                $this->logger->warning('No tracked message found for NotifierSentMessageEvent');
                return;
            }

            // Get sent message details
            $sentMessage = $event->getSentMessage();
            $messageId = $sentMessage?->getMessageId();

            // Update provider message ID
            $this->updateProviderMessageId($trackedMessage, $messageId);

            // Add sent event
            $this->messageTracker->addEvent(
                $trackedMessage,
                TrackedMessageEvent::TYPE_SENT,
                [
                    'provider_message_id' => $messageId,
                    'channel' => $this->getChannelType($message),
                    'transport' => $sentMessage?->getTransport(),
                    'symfony_event' => 'NotifierSentMessageEvent',
                ]
            );

            $this->logger->info('Notification sent event tracked', [
                'tracking_id' => $trackedMessage->getId(),
                'provider_message_id' => $messageId,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to track sent notification event', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->cleanupMessageMap($message);
        }
    }

    /**
     * Handle failed notification send
     */
    public function onFailedMessage(FailedMessageEvent $event): void
    {
        $message = $event->getMessage();
        
        try {
            $trackedMessage = $this->getTrackedMessage($message);
            
            if (!$trackedMessage) {
                // Create new tracking for failed message
                $notification = $this->getOrCreateNotification($event);
                $trackedMessage = $this->createTrackedMessage($message, $notification);
            }

            if (!$trackedMessage) {
                return;
            }

            $error = $event->getError();

            // Add failed event
            $this->messageTracker->addEvent(
                $trackedMessage,
                TrackedMessageEvent::TYPE_FAILED,
                [
                    'error_message' => $error?->getMessage(),
                    'error_class' => $error ? get_class($error) : null,
                    'channel' => $this->getChannelType($message),
                    'symfony_event' => 'NotifierFailedMessageEvent',
                ]
            );

            // Update failure info
            $trackedMessage->setFailureReason($error?->getMessage());
            $trackedMessage->incrementRetryCount();
            
            $this->entityManager->flush();

            $this->logger->error('Notification failed event tracked', [
                'tracking_id' => $trackedMessage->getId(),
                'error' => $error?->getMessage(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to track failed notification event', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->cleanupMessageMap($message);
        }
    }

    /**
     * Create tracked message based on message type
     */
    private function createTrackedMessage(
        MessageInterface $message,
        ?Notification $notification
    ): ?Message {
        if ($message instanceof NotifierSmsMessage) {
            return $this->createSmsMessage($message, $notification);
        }
        
        if ($message instanceof ChatMessage) {
            return $this->createChatMessage($message, $notification);
        }
        
        if ($message instanceof EmailMessage) {
            // Email messages are handled by MailerEventSubscriber
            return null;
        }

        $this->logger->warning('Unknown message type for tracking', [
            'type' => get_class($message),
        ]);

        return null;
    }

    /**
     * Create SMS message entity
     */
    private function createSmsMessage(
        NotifierSmsMessage $message,
        ?Notification $notification
    ): SmsMessage {
        $smsMessage = new SmsMessage();
        $smsMessage->setFromNumber($message->getFrom());
        $smsMessage->setNotification($notification);
        
        // Create recipient
        $recipient = new MessageRecipient();
        $recipient->setType(MessageRecipient::TYPE_TO);
        $recipient->setAddress($message->getPhone());
        $smsMessage->addRecipient($recipient);

        // Create content
        $content = new MessageContent();
        $content->setContentType('text/plain');
        $content->setBodyText($message->getSubject());
        $smsMessage->setContent($content);

        $this->entityManager->persist($smsMessage);
        $this->entityManager->flush();

        return $smsMessage;
    }

    /**
     * Create chat message entity
     */
    private function createChatMessage(
        ChatMessage $message,
        ?Notification $notification
    ): ?Message {
        $transport = $message->getTransport();
        
        if (str_contains($transport, 'slack')) {
            return $this->createSlackMessage($message, $notification);
        }
        
        if (str_contains($transport, 'telegram')) {
            return $this->createTelegramMessage($message, $notification);
        }

        return null;
    }

    /**
     * Create Slack message entity
     */
    private function createSlackMessage(
        ChatMessage $message,
        ?Notification $notification
    ): SlackMessage {
        $slackMessage = new SlackMessage();
        $slackMessage->setChannel($message->getRecipientId() ?? 'general');
        $slackMessage->setNotification($notification);

        // Create content
        $content = new MessageContent();
        $content->setContentType('text/plain');
        $content->setBodyText($message->getSubject());
        $slackMessage->setContent($content);

        $this->entityManager->persist($slackMessage);
        $this->entityManager->flush();

        return $slackMessage;
    }

    /**
     * Create Telegram message entity
     */
    private function createTelegramMessage(
        ChatMessage $message,
        ?Notification $notification
    ): TelegramMessage {
        $telegramMessage = new TelegramMessage();
        $telegramMessage->setChatId($message->getRecipientId() ?? '');
        $telegramMessage->setNotification($notification);

        // Create content
        $content = new MessageContent();
        $content->setContentType('text/plain');
        $content->setBodyText($message->getSubject());
        $telegramMessage->setContent($content);

        $this->entityManager->persist($telegramMessage);
        $this->entityManager->flush();

        return $telegramMessage;
    }

    /**
     * Get or create notification entity
     */
    private function getOrCreateNotification(MessageEvent|FailedMessageEvent $event): ?Notification
    {
        // Check if we have a Symfony notification
        $symfonyNotification = $this->extractSymfonyNotification($event);
        
        if (!$symfonyNotification) {
            return null;
        }

        // Check if already tracked
        $notificationId = spl_object_id($symfonyNotification);
        if (isset($this->notificationMap[$notificationId])) {
            return $this->notificationMap[$notificationId];
        }

        // Create new notification entity
        $notification = $this->notificationTracker->createFromSymfonyNotification($symfonyNotification);
        $this->notificationMap[$notificationId] = $notification;

        return $notification;
    }

    /**
     * Extract Symfony notification from event
     */
    private function extractSymfonyNotification(MessageEvent|FailedMessageEvent $event): ?SymfonyNotification
    {
        // This would need reflection or other mechanism to extract
        // the original notification from the event
        return null;
    }

    /**
     * Get channel type from message
     */
    private function getChannelType(MessageInterface $message): string
    {
        return match (true) {
            $message instanceof NotifierSmsMessage => 'sms',
            $message instanceof EmailMessage => 'email',
            $message instanceof ChatMessage && str_contains($message->getTransport(), 'slack') => 'slack',
            $message instanceof ChatMessage && str_contains($message->getTransport(), 'telegram') => 'telegram',
            default => 'unknown',
        };
    }

    /**
     * Get tracked message from map
     */
    private function getTrackedMessage(MessageInterface $message): ?Message
    {
        $messageId = spl_object_id($message);
        return $this->messageMap[$messageId] ?? null;
    }

    /**
     * Update provider message ID
     */
    private function updateProviderMessageId(Message $message, ?string $providerMessageId): void
    {
        if (!$providerMessageId) {
            return;
        }

        if ($message instanceof SmsMessage) {
            $message->setProviderMessageId($providerMessageId);
        }

        $metadata = $message->getMetadata();
        $metadata['provider_message_id'] = $providerMessageId;
        $message->setMetadata($metadata);
        
        $this->entityManager->flush();
    }

    /**
     * Clean up memory map
     */
    private function cleanupMessageMap(MessageInterface $message): void
    {
        $messageId = spl_object_id($message);
        unset($this->messageMap[$messageId]);
    }
}
```