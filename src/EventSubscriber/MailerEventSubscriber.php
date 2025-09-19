<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\EventSubscriber;

use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\MessageEvent;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Ulid;

class MailerEventSubscriber implements EventSubscriberInterface
{
    private array $messageMap = [];

    public function __construct(
        private readonly MessageTracker $messageTracker,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled = true
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => ['onMessage', 100],
            SentMessageEvent::class => ['onSentMessage', 0],
            FailedMessageEvent::class => ['onFailedMessage', 0],
        ];
    }

    public function onMessage(MessageEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $message = $event->getMessage();
        
        if (!$message instanceof Email) {
            return;
        }

        try {
            // Check if already tracked
            $trackingId = $this->getTrackingId($message);
            if ($trackingId && $this->findTrackedMessage($trackingId)) {
                $this->logger->debug('Message already tracked', ['tracking_id' => $trackingId]);
                return;
            }

            // Create tracking entity
            $trackedMessage = $this->messageTracker->trackEmail(
                $message,
                $this->extractTransportName($event),
                null,
                [
                    'queued' => $event->isQueued(),
                    'symfony_event' => 'MessageEvent',
                ]
            );

            // Store tracking ID in message headers
            $message->getHeaders()->addTextHeader('X-Tracking-ID', (string) $trackedMessage->getId());

            // Map for later reference
            $messageId = spl_object_id($message);
            $this->messageMap[$messageId] = $trackedMessage;

            $this->logger->info('Email tracked via Symfony MessageEvent', [
                'tracking_id' => (string) $trackedMessage->getId(),
                'subject' => $message->getSubject(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to track email in MessageEvent', [
                'error' => $e->getMessage(),
                'subject' => $message->getSubject(),
            ]);
        }
    }

    public function onSentMessage(SentMessageEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

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

            $sentMessage = $event->getMessage();
            $providerMessageId = $sentMessage->getMessageId();
            
            if ($trackedMessage instanceof EmailMessage) {
                $trackedMessage->setMessageId($providerMessageId);
            }

            $this->messageTracker->addEvent(
                $trackedMessage,
                MessageEvent::TYPE_SENT,
                [
                    'provider_message_id' => $providerMessageId,
                    'debug' => $sentMessage->getDebug(),
                    'symfony_event' => 'SentMessageEvent',
                ]
            );

            $this->storeProviderMapping($trackedMessage, $providerMessageId);

            $this->logger->info('Email sent event tracked', [
                'tracking_id' => (string) $trackedMessage->getId(),
                'provider_message_id' => $providerMessageId,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to track sent email event', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->cleanupMessageMap($message);
        }
    }

    public function onFailedMessage(FailedMessageEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $message = $event->getMessage();
        
        if (!$message instanceof Email) {
            return;
        }

        try {
            $trackedMessage = $this->getTrackedMessage($message);
            
            if (!$trackedMessage) {
                $trackedMessage = $this->messageTracker->trackEmail(
                    $message,
                    null,
                    null,
                    ['symfony_event' => 'FailedMessageEvent']
                );
            }

            $error = $event->getError();
            $this->messageTracker->addEvent(
                $trackedMessage,
                MessageEvent::TYPE_FAILED,
                [
                    'error_message' => $error?->getMessage(),
                    'error_class' => $error ? get_class($error) : null,
                    'error_code' => $error?->getCode(),
                    'symfony_event' => 'FailedMessageEvent',
                ]
            );

            $trackedMessage->setFailureReason($error?->getMessage());
            $trackedMessage->incrementRetryCount();
            
            $this->entityManager->flush();

            $this->logger->error('Email failed event tracked', [
                'tracking_id' => (string) $trackedMessage->getId(),
                'error' => $error?->getMessage(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to track failed email event', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->cleanupMessageMap($message);
        }
    }

    private function getTrackedMessage(Email $message): ?EmailMessage
    {
        $messageId = spl_object_id($message);
        if (isset($this->messageMap[$messageId])) {
            return $this->messageMap[$messageId];
        }

        $trackingId = $this->getTrackingId($message);
        if ($trackingId) {
            return $this->findTrackedMessage($trackingId);
        }

        return null;
    }

    private function getTrackingId(Email $message): ?string
    {
        $header = $message->getHeaders()->get('X-Tracking-ID');
        return $header?->getBody();
    }

    private function findTrackedMessage(string $trackingId): ?EmailMessage
    {
        try {
            $ulid = Ulid::fromString($trackingId);
            return $this->entityManager->getRepository(EmailMessage::class)->find($ulid);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function extractTransportName(MessageEvent $event): ?string
    {
        // Implementation depends on your transport configuration
        return null;
    }

    private function storeProviderMapping(EmailMessage $message, string $providerMessageId): void
    {
        $metadata = $message->getMetadata();
        $metadata['provider_message_id'] = $providerMessageId;
        $message->setMetadata($metadata);
        $this->entityManager->flush();
    }

    private function cleanupMessageMap(Email $message): void
    {
        $messageId = spl_object_id($message);
        unset($this->messageMap[$messageId]);
    }
}