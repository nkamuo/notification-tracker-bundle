<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\SmsMessage;
use Nkamuo\NotificationTrackerBundle\Entity\SlackMessage;
use Nkamuo\NotificationTrackerBundle\Entity\MessageContent;
use Nkamuo\NotificationTrackerBundle\Entity\MessageRecipient;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationDirection;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationStatus;
use Nkamuo\NotificationTrackerBundle\Enum\MessageStatus;
use Nkamuo\NotificationTrackerBundle\Message\SendNotificationMessage;
use Nkamuo\NotificationTrackerBundle\Message\SendChannelMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
class SendNotificationMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(SendNotificationMessage $message): void
    {
        $notification = $this->entityManager->getRepository(Notification::class)
            ->find($message->getNotificationId());

        if (!$notification) {
            $this->logger->error('Notification not found', [
                'notification_id' => (string) $message->getNotificationId(),
            ]);
            return;
        }

        if (!in_array($notification->getStatus(), [
            NotificationStatus::DRAFT,
            NotificationStatus::SCHEDULED,
            NotificationStatus::QUEUED
        ])) {
            $this->logger->warning('Notification not in sendable status', [
                'notification_id' => (string) $notification->getId(),
                'status' => $notification->getStatus()->value,
            ]);
            return;
        }

        try {
            // Update notification status
            $notification->setStatus(Notification::STATUS_SENDING);
            $this->entityManager->flush();

            $channels = $message->getChannel() 
                ? [$message->getChannel()] 
                : $notification->getChannels();

            $recipients = $message->getRecipientOverrides() ?? $notification->getRecipients() ?? [];

            // Create and dispatch individual channel messages
            foreach ($channels as $channel) {
                $this->createAndDispatchChannelMessages($notification, $channel, $recipients);
            }

            // Update notification status based on whether messages were created
            $messageCount = $this->entityManager->getRepository($this->getMessageClassForChannel('email'))
                ->createQueryBuilder('m')
                ->select('COUNT(m.id)')
                ->where('m.notification = :notification')
                ->setParameter('notification', $notification)
                ->getQuery()
                ->getSingleScalarResult();

            if ($messageCount > 0) {
                $notification->setStatus(NotificationStatus::QUEUED);
                $notification->setDirection(NotificationDirection::OUTBOUND);
            } else {
                $notification->setStatus(NotificationStatus::FAILED);
            }

            $this->entityManager->flush();

            $this->logger->info('Notification processed for sending', [
                'notification_id' => (string) $notification->getId(),
                'channels' => $channels,
                'message_count' => $messageCount,
            ]);

        } catch (\Exception $e) {
            $notification->setStatus(Notification::STATUS_FAILED);
            $this->entityManager->flush();

            $this->logger->error('Failed to process notification', [
                'notification_id' => (string) $notification->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function createAndDispatchChannelMessages(
        Notification $notification, 
        string $channel, 
        array $recipients
    ): void {
        // Filter recipients for this channel
        $channelRecipients = array_filter($recipients, 
            fn($r) => ($r['channel'] ?? $channel) === $channel
        );

        foreach ($channelRecipients as $recipientData) {
            // Create the appropriate message entity
            $messageEntity = $this->createMessageEntity($channel, $notification, $recipientData);
            
            // Persist the message entity
            $this->entityManager->persist($messageEntity);
            $this->entityManager->flush();

            // Create messenger message for sending
            $channelMessage = new SendChannelMessage(
                $messageEntity->getId(),
                $channel,
                $recipientData,
                $notification->getMetadata()
            );

            // Calculate delay based on scheduling
            $delayMs = $this->calculateDelayForMessage($notification, $recipientData);
            
            if ($delayMs > 0) {
                // Dispatch with delay
                $this->messageBus->dispatch($channelMessage, [
                    new DelayStamp($delayMs)
                ]);
                
                $this->logger->info('Channel message scheduled', [
                    'message_id' => (string) $messageEntity->getId(),
                    'channel' => $channel,
                    'delay_ms' => $delayMs,
                ]);
            } else {
                // Dispatch immediately
                $this->messageBus->dispatch($channelMessage);
                
                $this->logger->info('Channel message dispatched immediately', [
                    'message_id' => (string) $messageEntity->getId(),
                    'channel' => $channel,
                ]);
            }
        }
    }

    private function createMessageEntity(string $channel, Notification $notification, array $recipientData)
    {
        $messageClass = $this->getMessageClassForChannel($channel);
        $message = new $messageClass();
        
        $message->setDirection(NotificationDirection::OUTBOUND);
        $message->setStatus(MessageStatus::PENDING);
        $message->setNotification($notification);

        // Handle individual message scheduling
        if (isset($recipientData['scheduledAt'])) {
            $scheduledAt = new \DateTimeImmutable($recipientData['scheduledAt']);
            $message->scheduleFor($scheduledAt, true); // true = override
        }

        // Create content
        $content = new MessageContent();
        $content->setMessage($message);
        $content->setBodyText($notification->getContent());
        
        // Handle channel-specific content
        $metadata = $notification->getMetadata();
        switch ($channel) {
            case 'email':
                if (isset($metadata['html_content'])) {
                    $content->setBodyHtml($metadata['html_content']);
                }
                break;
            case 'slack':
                if (isset($metadata['slack_blocks']) || isset($metadata['slack_attachments'])) {
                    $slackData = [];
                    if (isset($metadata['slack_blocks'])) {
                        $slackData['blocks'] = $metadata['slack_blocks'];
                    }
                    if (isset($metadata['slack_attachments'])) {
                        $slackData['attachments'] = $metadata['slack_attachments'];
                    }
                    $content->setStructuredData($slackData);
                }
                break;
        }
        
        $message->setContent($content);

        // Create recipient
        $recipient = new MessageRecipient();
        $recipient->setMessage($message);
        $recipient->setAddress($this->getRecipientAddress($channel, $recipientData));
        $recipient->setName($recipientData['name'] ?? null);
        $recipient->setType(MessageRecipient::TYPE_TO);
        $recipient->setStatus(MessageRecipient::STATUS_PENDING);
        $message->addRecipient($recipient);

        // Add labels from notification
        foreach ($notification->getLabels() as $label) {
            $message->addLabel($label);
        }

        return $message;
    }

    private function getMessageClassForChannel(string $channel): string
    {
        return match (strtolower($channel)) {
            'email' => EmailMessage::class,
            'sms' => SmsMessage::class,
            'slack' => SlackMessage::class,
            default => throw new \InvalidArgumentException("Unsupported channel: {$channel}")
        };
    }

    private function getRecipientAddress(string $channel, array $recipientData): string
    {
        return match (strtolower($channel)) {
            'email' => $recipientData['email'] ?? $recipientData['address'],
            'sms' => $recipientData['phone'] ?? $recipientData['address'],
            'slack' => $recipientData['channel'] ?? $recipientData['address'],
            default => $recipientData['address']
        };
    }

    private function calculateDelayForMessage(Notification $notification, array $recipientData): int
    {
        $now = new \DateTimeImmutable();
        
        // Check for recipient-specific scheduling override
        if (isset($recipientData['scheduledAt'])) {
            $scheduledAt = new \DateTimeImmutable($recipientData['scheduledAt']);
            if ($scheduledAt > $now) {
                return ($scheduledAt->getTimestamp() - $now->getTimestamp()) * 1000;
            }
        }
        
        // Check for notification-level scheduling
        if ($notification->getScheduledAt() && $notification->getScheduledAt() > $now) {
            return ($notification->getScheduledAt()->getTimestamp() - $now->getTimestamp()) * 1000;
        }
        
        return 0; // Send immediately
    }
}
