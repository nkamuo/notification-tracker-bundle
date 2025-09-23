<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\SmsMessage;
use Nkamuo\NotificationTrackerBundle\Entity\MessageContent;
use Nkamuo\NotificationTrackerBundle\Entity\MessageRecipient;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationDirection;
use Nkamuo\NotificationTrackerBundle\Enum\MessageStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Notification\Notification as SymfonyNotification;

class NotificationTracker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly EventEnrichmentService $enrichmentService
    ) {
    }

    public function createFromSymfonyNotification(SymfonyNotification $symfonyNotification): Notification
    {
        $notification = new Notification();
        $notification->setType('symfony_notification');
        $notification->setSubject($symfonyNotification->getSubject());
        $notification->setImportance($symfonyNotification->getImportance());

        // $recipients = $symfonyNotification->getRecipients();

        $channels = [];
        // TODO: Fix this when needed - getChannels may require recipient parameter
        // foreach ($symfonyNotification->getChannels() as $channel) {
        //     $channels[] = $channel;
        // }
        $notification->setChannels($channels);
        
        // Dispatch creation event for enrichment
        $context = ['source' => 'symfony_notification'];
        $shouldContinue = $this->enrichmentService->enrichNotificationOnCreation($notification, $context);
        
        if (!$shouldContinue) {
            $this->logger->info('Notification creation was stopped by event listener');
            return $notification;
        }
        
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        
        return $notification;
    }

    /**
     * Create an inbound message from external webhook data
     */
    public function createInboundMessage(
        string $type,
        string $from,
        array $to,
        ?string $subject = null,
        array $content = [],
        array $metadata = []
    ): Message {
        // Create the appropriate message type
        $message = match($type) {
            'email' => new EmailMessage(),
            'sms' => new SmsMessage(),
            default => throw new \InvalidArgumentException("Unsupported message type: {$type}")
        };

        $message->setDirection(NotificationDirection::INBOUND);
        $message->setStatus(MessageStatus::DELIVERED); // Inbound messages are already delivered
        $message->setMetadata($metadata);

        // Add sender as recipient (for inbound messages, sender becomes recipient)
        $fromRecipient = new MessageRecipient();
        $fromRecipient->setMessage($message);
        $fromRecipient->setAddress($from);
        $fromRecipient->setType(MessageRecipient::TYPE_TO);
        $fromRecipient->setStatus(MessageRecipient::STATUS_DELIVERED);
        $message->addRecipient($fromRecipient);

        // Add recipients (for inbound messages, these are the original "to" addresses)
        foreach ($to as $toAddress) {
            $recipient = new MessageRecipient();
            $recipient->setMessage($message);
            $recipient->setAddress($toAddress);
            $recipient->setType(MessageRecipient::TYPE_TO);
            $recipient->setStatus(MessageRecipient::STATUS_DELIVERED);
            $message->addRecipient($recipient);
        }

        // Dispatch creation event for enrichment
        $context = [
            'source' => 'inbound_webhook',
            'provider' => $type,
            'from' => $from,
            'to' => $to,
            'subject' => $subject,
            'content' => $content
        ];
        
        $shouldContinue = $this->enrichmentService->enrichMessageOnCreation($message, $context);
        
        if (!$shouldContinue) {
            $this->logger->info('Message creation was stopped by event listener');
            return $message;
        }

        // Dispatch inbound message event for custom processing
        $rawData = [
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'subject' => $subject,
            'content' => $content,
            'metadata' => $metadata
        ];
        
        $this->enrichmentService->processInboundMessage($message, $rawData, $type, $context);

        return $message;
    }
}