<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\EventSubscriber;

use Nkamuo\NotificationTrackerBundle\Event\NotificationCreatedEvent;
use Nkamuo\NotificationTrackerBundle\Event\MessageCreatedEvent;
use Nkamuo\NotificationTrackerBundle\Service\ReferenceExtractor;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber that automatically extracts references from notifications and messages
 */
class ReferenceExtractionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ReferenceExtractor $referenceExtractor,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            NotificationCreatedEvent::class => [
                ['onNotificationCreated', 10], // Priority 10 to run early
            ],
            MessageCreatedEvent::class => [
                ['onMessageCreated', 10], // Priority 10 to run early  
            ],
        ];
    }

    /**
     * Handle notification created event
     * 
     * @param NotificationCreatedEvent $event
     */
    public function onNotificationCreated(NotificationCreatedEvent $event): void
    {
        $notification = $event->getNotification();
        $context = $event->getContext();
        
        try {
            // Extract references from context
            $refs = $this->referenceExtractor->extractFromContext($context);
            
            if (!empty($refs)) {
                $count = $this->referenceExtractor->applyToNotification($notification, $refs);
                
                $this->logger->info('Extracted references for notification', [
                    'notification_id' => (string) $notification->getId(),
                    'refs_extracted' => $count,
                    'refs' => array_keys($refs)
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to extract references for notification', [
                'notification_id' => (string) $notification->getId(),
                'error' => $e->getMessage(),
                'exception' => $e
            ]);
        }
    }

    /**
     * Handle message created event
     * 
     * @param MessageCreatedEvent $event
     */
    public function onMessageCreated(MessageCreatedEvent $event): void
    {
        $message = $event->getMessage();
        $context = $event->getContext();
        
        try {
            // Extract references from context
            $refs = $this->referenceExtractor->extractFromContext($context);
            
            if (!empty($refs)) {
                $count = $this->referenceExtractor->applyToMessage($message, $refs);
                
                $this->logger->info('Extracted references for message', [
                    'message_id' => (string) $message->getId(),
                    'refs_extracted' => $count,
                    'refs' => array_keys($refs)
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to extract references for message', [
                'message_id' => (string) $message->getId(),
                'error' => $e->getMessage(),
                'exception' => $e
            ]);
        }
    }
}
