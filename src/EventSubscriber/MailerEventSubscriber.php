<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\EventSubscriber;

use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\MessageEvent as TrackedMessageEvent;
use Nkamuo\NotificationTrackerBundle\Entity\MessageContent;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTrackingStamp;
use Nkamuo\NotificationTrackerBundle\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Event\MessageEvent;
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
                    TrackedMessageEvent::TYPE_QUEUED,
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

            // Check for stamp ID in headers (set by middleware)
            $stampId = null;
            if ($message->getHeaders()->has('X-Stamp-ID')) {
                $stampId = $message->getHeaders()->get('X-Stamp-ID')->getBodyAsString();
                
                // Check if we already tracked this via the stamp
                $existingMessage = $this->messageRepository->findByStampId($stampId);
                if ($existingMessage) {
                    $this->logger->debug('Message already tracked via stamp ID', [
                        'stamp_id' => $stampId,
                        'tracking_id' => (string) $existingMessage->getId()
                    ]);
                    
                    // Map for later reference
                    $messageId = spl_object_id($message);
                    $this->messageMap[$messageId] = $existingMessage;
                    
                    // Store tracking ID in message headers for later reference
                    $message->getHeaders()->addTextHeader('X-Tracking-ID', (string) $existingMessage->getId());
                    
                    return;
                }
            }

            // Generate content fingerprint for analytics purposes
            $contentFingerprint = $this->generateContentFingerprint($message);

            // Create new tracking entity for first attempt (fallback for direct mailer usage)
            $trackedMessage = $this->messageTracker->trackEmail(
                $message,
                $this->extractTransportName($event),
                null,
                [
                    'queued' => $event->isQueued(),
                    'symfony_event' => 'MessageEvent',
                    'content_fingerprint' => $contentFingerprint,
                    'stamp_id' => $stampId,
                    'source' => 'fallback_tracking', // This indicates it wasn't tracked early
                ]
            );

            // Store tracking ID in message headers
            $message->getHeaders()->addTextHeader('X-Tracking-ID', (string) $trackedMessage->getId());

            // Map for later reference
            $messageId = spl_object_id($message);
            $this->messageMap[$messageId] = $trackedMessage;

            $this->logger->info('Email tracked via Symfony MessageEvent (fallback)', [
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
                    TrackedMessageEvent::TYPE_QUEUED,
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
                // This is a new message - track it immediately for early visibility
                $message = $envelope->getMessage();
                if ($message instanceof \Symfony\Component\Mailer\Messenger\SendEmailMessage) {
                    $email = $message->getMessage();
                    if ($email instanceof Email) {
                        $trackedMessage = $this->trackEmailEarly($email, $stamp, array_keys($event->getSenders()));
                        
                        $this->logger->info('Message tracked early via SendMessageToTransports', [
                            'stamp_id' => $stamp->getId(),
                            'tracking_id' => (string) $trackedMessage->getId(),
                            'subject' => $email->getSubject(),
                        ]);
                    }
                }
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
                // Check for stamp ID to see if we can find existing tracking
                $stampId = null;
                if ($message->getHeaders()->has('X-Stamp-ID')) {
                    $stampId = $message->getHeaders()->get('X-Stamp-ID')->getBodyAsString();
                    $trackedMessage = $this->messageRepository->findByStampId($stampId);
                }
                
                // Auto-track untracked messages only if no existing tracking found
                if (!$trackedMessage) {
                    $this->logger->info('Auto-tracking untracked message in SentMessageEvent', [
                        'subject' => $message->getSubject(),
                        'stamp_id' => $stampId,
                    ]);
                    
                    $trackedMessage = $this->autoTrackMessage($message);
                    if (!$trackedMessage) {
                        $this->logger->warning('Failed to auto-track message for SentMessageEvent', [
                            'subject' => $message->getSubject(),
                        ]);
                        return;
                    }
                }
            }

            $sentMessage = $event->getMessage();
            $providerMessageId = $sentMessage->getMessageId();
            
            if ($trackedMessage instanceof EmailMessage) {
                $trackedMessage->setMessageId($providerMessageId);
            }

            $this->messageTracker->addEvent(
                $trackedMessage,
                TrackedMessageEvent::TYPE_SENT,
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
                // Check for stamp ID to see if we can find existing tracking
                $stampId = null;
                if ($message->getHeaders()->has('X-Stamp-ID')) {
                    $stampId = $message->getHeaders()->get('X-Stamp-ID')->getBodyAsString();
                    $trackedMessage = $this->messageRepository->findByStampId($stampId);
                }
                
                // If still no tracked message, auto-track as last resort
                if (!$trackedMessage) {
                    $trackedMessage = $this->messageTracker->trackEmail(
                        $message,
                        null,
                        null,
                        [
                            'symfony_event' => 'FailedMessageEvent',
                            'stamp_id' => $stampId,
                            'source' => 'failed_message_fallback'
                        ]
                    );
                    
                    $this->logger->warning('Auto-tracked message in FailedMessageEvent (no prior tracking found)', [
                        'tracking_id' => (string) $trackedMessage->getId(),
                        'subject' => $message->getSubject(),
                        'stamp_id' => $stampId,
                    ]);
                }
            }

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
        // 1. Check object ID mapping first (fastest)
        $messageId = spl_object_id($message);
        if (isset($this->messageMap[$messageId])) {
            return $this->messageMap[$messageId];
        }

        // 2. Check for X-Tracking-ID header
        $trackingId = $this->getTrackingId($message);
        if ($trackingId) {
            return $this->findTrackedMessage($trackingId);
        }

        // 3. Check for stamp ID header (for middleware-tracked messages)
        if ($message->getHeaders()->has('X-Stamp-ID')) {
            $stampId = $message->getHeaders()->get('X-Stamp-ID')->getBodyAsString();
            $trackedMessage = $this->messageRepository->findByStampId($stampId);
            if ($trackedMessage) {
                return $trackedMessage;
            }
        }

        // 4. Last resort: content-based lookup (for direct mailer usage like mailer:test)
        $contentFingerprint = $this->generateContentFingerprint($message);
        
        // Look for messages with same content fingerprint created recently (within 5 minutes)
        $recentThreshold = new \DateTime('-5 minutes');
        $trackedMessage = $this->messageRepository->findRecentByContentFingerprint(
            $contentFingerprint, 
            $recentThreshold
        );
        
        if ($trackedMessage) {
            $this->logger->debug('Found message via content fingerprint', [
                'tracking_id' => (string) $trackedMessage->getId(),
                'content_fingerprint' => $contentFingerprint,
                'subject' => $message->getSubject(),
            ]);
            
            // Add to object map for future lookups
            $this->messageMap[$messageId] = $trackedMessage;
            
            return $trackedMessage;
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

    /**
     * Track an email early when it hits the SendMessageToTransports event
     * This provides immediate visibility even before transport attempts
     */
    private function trackEmailEarly(Email $email, NotificationTrackingStamp $stamp, array $transports): EmailMessage
    {
        // Generate content fingerprint for analytics purposes
        $contentFingerprint = $this->generateContentFingerprint($email);

        // Track the email with stamp ID
        $trackedMessage = $this->messageTracker->trackEmail(
            $email,
            implode(',', $transports), // transport names as comma-separated string
            null, // notification will be auto-created
            [
                'stamp_id' => $stamp->getId(),
                'content_fingerprint' => $contentFingerprint,
                'transports' => $transports,
                'symfony_event' => 'SendMessageToTransportsEvent',
                'tracked_early' => true,
            ]
        );

        // Store tracking ID in message headers for later reference
        $email->getHeaders()->addTextHeader('X-Tracking-ID', (string) $trackedMessage->getId());

        // Map for later reference
        $messageId = spl_object_id($email);
        $this->messageMap[$messageId] = $trackedMessage;

        return $trackedMessage;
    }
}