<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\EventSubscriber;

use Nkamuo\NotificationTrackerBundle\Event\MessageCreatedEvent;
use Nkamuo\NotificationTrackerBundle\Event\NotificationCreatedEvent;
use Nkamuo\NotificationTrackerBundle\Event\InboundMessageEvent;
use Nkamuo\NotificationTrackerBundle\Service\EventEnrichmentService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sample event subscriber to demonstrate auto-labeling functionality
 * This shows how applications can automatically add labels based on message content
 */
class AutoLabelEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EventEnrichmentService $enrichmentService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NotificationCreatedEvent::NAME => 'onNotificationCreated',
            MessageCreatedEvent::NAME => 'onMessageCreated',
            InboundMessageEvent::NAME => 'onInboundMessage',
        ];
    }

    public function onNotificationCreated(NotificationCreatedEvent $event): void
    {
        $notification = $event->getNotification();
        
        // Auto-label based on notification type
        if ($notification->getType() === 'marketing') {
            $this->enrichmentService->addLabel($notification, 'marketing', 'Automatically detected marketing notification');
        }
        
        // Auto-label based on subject content
        $subject = $notification->getSubject();
        if ($subject && str_contains(strtolower($subject), 'urgent')) {
            $this->enrichmentService->addLabel($notification, 'urgent');
        }
        
        if ($subject && str_contains(strtolower($subject), 'invoice')) {
            $this->enrichmentService->addLabel($notification, 'billing');
        }
        
        // Add metadata for tracking
        $this->enrichmentService->addMetadata($notification, 'auto_labeled_at', new \DateTimeImmutable());
        $this->enrichmentService->addMetadata($notification, 'source_context', $event->getContext());
    }

    public function onMessageCreated(MessageCreatedEvent $event): void
    {
        $message = $event->getMessage();
        
        // Auto-label based on message direction
        if ($message->getDirection() === 'inbound') {
            $this->enrichmentService->addLabel($message, 'inbound');
        } else {
            $this->enrichmentService->addLabel($message, 'outbound');
        }
        
        // Auto-label based on recipient domain
        foreach ($message->getRecipients() as $recipient) {
            $email = $recipient->getAddress();
            if (str_contains($email, '@gmail.com')) {
                $this->enrichmentService->addLabel($message, 'gmail-recipient');
            } elseif (str_contains($email, '@company.com')) {
                $this->enrichmentService->addLabel($message, 'internal');
            }
        }
    }

    public function onInboundMessage(InboundMessageEvent $event): void
    {
        $message = $event->getMessage();
        $rawData = $event->getRawData();
        $provider = $event->getProvider();
        
        // Auto-label based on provider
        $this->enrichmentService->addLabel($message, "provider-{$provider}");
        
        // Auto-label based on content analysis
        if (isset($rawData['subject'])) {
            $subject = strtolower($rawData['subject']);
            
            if (str_contains($subject, 'support') || str_contains($subject, 'help')) {
                $this->enrichmentService->addLabel($message, 'support-request');
            }
            
            if (str_contains($subject, 'complaint') || str_contains($subject, 'issue')) {
                $this->enrichmentService->addLabel($message, 'complaint');
            }
        }
        
        // Add metadata for webhook processing
        $this->enrichmentService->addMetadata($message, 'webhook_provider', $provider);
        $this->enrichmentService->addMetadata($message, 'processed_at', new \DateTimeImmutable());
        $this->enrichmentService->addMetadata($message, 'raw_webhook_data', $rawData);
    }
}
