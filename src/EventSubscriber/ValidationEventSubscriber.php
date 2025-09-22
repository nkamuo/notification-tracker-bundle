<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\EventSubscriber;

use Nkamuo\NotificationTrackerBundle\Event\NotificationPreSendEvent;
use Nkamuo\NotificationTrackerBundle\Event\MessagePreProcessEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sample event subscriber to demonstrate validation and business rules
 * This shows how applications can enforce custom business logic
 */
class ValidationEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NotificationPreSendEvent::NAME => 'onNotificationPreSend',
            MessagePreProcessEvent::NAME => 'onMessagePreProcess',
        ];
    }

    public function onNotificationPreSend(NotificationPreSendEvent $event): void
    {
        $notification = $event->getNotification();
        
        // Example: Block sending notifications during maintenance windows
        $maintenanceStart = new \DateTimeImmutable('2024-01-01 02:00:00');
        $maintenanceEnd = new \DateTimeImmutable('2024-01-01 04:00:00');
        $now = new \DateTimeImmutable();
        
        if ($now >= $maintenanceStart && $now <= $maintenanceEnd) {
            $this->logger->warning('Blocking notification send during maintenance window', [
                'notification_id' => $notification->getId()->toRfc4122(),
                'maintenance_start' => $maintenanceStart->format('c'),
                'maintenance_end' => $maintenanceEnd->format('c')
            ]);
            
            $event->cancelSending();
            return;
        }
        
        // Example: Validate notification has required content
        if (!$notification->getSubject()) {
            $this->logger->error('Blocking notification send: missing subject', [
                'notification_id' => $notification->getId()->toRfc4122()
            ]);
            
            $event->cancelSending();
            return;
        }
        
        // Example: Rate limiting for marketing notifications
        foreach ($notification->getLabels() as $label) {
            if ($label->getName() === 'marketing') {
                // Check if too many marketing notifications sent recently
                // This is a simplified example - in practice you'd check the database
                $context = $event->getContext();
                if (isset($context['recent_marketing_count']) && $context['recent_marketing_count'] > 10) {
                    $this->logger->warning('Blocking marketing notification: rate limit exceeded', [
                        'notification_id' => $notification->getId()->toRfc4122(),
                        'recent_count' => $context['recent_marketing_count']
                    ]);
                    
                    $event->cancelSending();
                    return;
                }
                break;
            }
        }
        
        $this->logger->info('Notification pre-send validation passed', [
            'notification_id' => $notification->getId()->toRfc4122(),
            'subject' => $notification->getSubject()
        ]);
    }

    public function onMessagePreProcess(MessagePreProcessEvent $event): void
    {
        $message = $event->getMessage();
        
        // Example: Block processing messages from blocked domains
        $blockedDomains = ['spam.example.com', 'blocked.test'];
        
        foreach ($message->getRecipients() as $recipient) {
            $email = $recipient->getAddress();
            $domain = substr($email, strpos($email, '@') + 1);
            
            if (in_array($domain, $blockedDomains)) {
                $this->logger->warning('Blocking message processing: blocked domain', [
                    'message_id' => $message->getId()->toRfc4122(),
                    'blocked_domain' => $domain,
                    'recipient' => $email
                ]);
                
                $event->cancelProcessing();
                return;
            }
        }
        
        // Example: Validate message content
        if ($message->getDirection() === 'outbound' && $message->getRecipients()->isEmpty()) {
            $this->logger->error('Blocking message processing: no recipients', [
                'message_id' => $message->getId()->toRfc4122()
            ]);
            
            $event->cancelProcessing();
            return;
        }
        
        $this->logger->info('Message pre-process validation passed', [
            'message_id' => $message->getId()->toRfc4122(),
            'direction' => $message->getDirection(),
            'recipients_count' => $message->getRecipients()->count()
        ]);
    }
}
