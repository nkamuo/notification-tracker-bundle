<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\EventSubscriber;

use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\MessageEvent;
use Nkamuo\NotificationTrackerBundle\Entity\MessageContent;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTrackingStamp;
use Nkamuo\NotificationTrackerBundle\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Ulid;

class MailerEventSubscriber implements EventSubscriberInterface
{
    private array $messageMap = [];

    public function __construct(
        private readonly MessageTracker $messageTracker,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageRepository $messageRepository,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled = true
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => ['onMessage', 100],
            SendMessageToTransportsEvent::class => ['onSendMessageToTransports', 100],
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
            // Check if already tracked by custom X-Tracking-ID header first
            $trackingId = $this->getTrackingId($message);
            if ($trackingId && $trackedMessage = $this->findTrackedMessage($trackingId)) {
                $this->logger->debug('Message already tracked via X-Tracking-ID', ['tracking_id' => $trackingId]);
                
                // Map for later reference and add retry event
                $messageId = spl_object_id($message);
                $this->messageMap[$messageId] = $trackedMessage;
                
                // Add retry event
                $this->messageTracker->addEvent(
                    $trackedMessage,
                    MessageEvent::TYPE_QUEUED,
                    [
                        'retry_attempt' => true,
                        'transport' => $this->extractTransportName($event),
                        'symfony_event' => 'MessageEvent',
                    ]
                );
                
                $this->logger->info('Email retry tracked', [
                    'tracking_id' => (string) $trackedMessage->getId(),
                    'subject' => $message->getSubject(),
                ]);
                return;
            }

            // Generate content fingerprint for analytics purposes
            $contentFingerprint = $this->generateContentFingerprint($message);

            // Check for stamp ID in headers (set by middleware)
            $stampId = null;
            if ($message->getHeaders()->has('X-Stamp-ID')) {
                $stampId = $message->getHeaders()->get('X-Stamp-ID')->getBodyAsString();
            }

            // Create new tracking entity for first attempt
            $trackedMessage = $this->messageTracker->trackEmail(
                $message,
                $this->extractTransportName($event),
                null,
                [
                    'queued' => $event->isQueued(),
                    'symfony_event' => 'MessageEvent',
                    'content_fingerprint' => $contentFingerprint,
                    'stamp_id' => $stampId,
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

    public function onSendMessageToTransports(SendMessageToTransportsEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $envelope = $event->getEnvelope();
        $stamp = $envelope->last(NotificationTrackingStamp::class);
        
        if (!$stamp) {
            // No stamp found, this shouldn't happen if middleware is working
            $this->logger->warning('No NotificationTrackingStamp found in envelope');
            return;
        }

        try {
            // Check if we already have a message with this stamp ID
            $existingMessage = $this->messageRepository->findByStampId($stamp->getId());
            
            if ($existingMessage) {
                // This is a retry - add a retry event
                $this->messageTracker->addEvent(
                    $existingMessage,
                    \Nkamuo\NotificationTrackerBundle\Entity\MessageEvent::TYPE_QUEUED,
                    [
                        'retry_attempt' => true,
                        'stamp_id' => $stamp->getId(),
                        'symfony_event' => 'SendMessageToTransportsEvent',
                        'transports' => array_keys($event->getSenders()),
                    ]
                );
                
                $this->logger->info('Message retry detected via stamp', [
                    'stamp_id' => $stamp->getId(),
                    'tracking_id' => (string) $existingMessage->getId(),
                    'message_type' => get_class($envelope->getMessage()),
                ]);
            } else {
                // This is a new message - we'll track it when the actual email event fires
                $this->logger->debug('New message with stamp detected', [
                    'stamp_id' => $stamp->getId(),
                    'message_type' => get_class($envelope->getMessage()),
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to process message stamp', [
                'error' => $e->getMessage(),
                'stamp_id' => $stamp->getId(),
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
                // Auto-track untracked messages (e.g., from mailer:test command)
                $this->logger->info('Auto-tracking untracked message in SentMessageEvent', [
                    'subject' => $message->getSubject(),
                ]);
                
                $trackedMessage = $this->autoTrackMessage($message);
                if (!$trackedMessage) {
                    $this->logger->warning('Failed to auto-track message for SentMessageEvent', [
                        'subject' => $message->getSubject(),
                    ]);
                    return;
                }
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

    /**
     * Auto-track a message that wasn't tracked before sending
     * This handles cases like mailer:test command or direct Mailer usage
     */
    private function autoTrackMessage(Email $message): ?EmailMessage
    {
        try {
            // Use the MessageTracker to properly track the email
            $trackedMessage = $this->messageTracker->trackEmail(
                $message,
                null, // transportName
                null, // notification
                [
                    'auto_tracked' => true,
                    'source' => 'MailerEventSubscriber',
                    'original_event' => 'SentMessageEvent'
                ]
            );
            
            // Store in message map for future events
            $messageId = spl_object_id($message);
            $this->messageMap[$messageId] = $trackedMessage;
            
            return $trackedMessage;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to auto-track message', [
                'error' => $e->getMessage(),
                'subject' => $message->getSubject(),
            ]);
            return null;
        }
    }

    private function generateContentFingerprint(Email $message): string
    {
        // Create a hash based on message content that should be consistent across retries
        $content = [
            'subject' => $message->getSubject(),
            'from' => $message->getFrom() ? $message->getFrom()[0]->toString() : '',
            'to' => array_map(fn($addr) => $addr->toString(), $message->getTo()),
            'cc' => array_map(fn($addr) => $addr->toString(), $message->getCc()),
            'bcc' => array_map(fn($addr) => $addr->toString(), $message->getBcc()),
            'text_body' => $message->getTextBody(),
            'html_body' => $message->getHtmlBody(),
        ];
        
        return hash('sha256', serialize($content));
    }
}