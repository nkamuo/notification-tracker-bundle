<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Message\SendNotificationMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Psr\Log\LoggerInterface;

class NotificationSender
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Send notification using Symfony Messenger with DelayStamp for scheduling
     */
    public function sendNotification(Notification $notification, \DateTimeImmutable $now = null): array
    {
        $now = $now ?? new \DateTimeImmutable();
        
        if (!in_array($notification->getStatus(), [Notification::STATUS_DRAFT, Notification::STATUS_SCHEDULED, Notification::STATUS_QUEUED])) {
            throw new \InvalidArgumentException(sprintf('Cannot send notification with status "%s"', $notification->getStatus()));
        }

        try {
            $delayMs = $this->calculateNotificationDelay($notification, $now);
            $isScheduled = $delayMs > 0;

            // Create messenger message for the notification
            $message = new SendNotificationMessage($notification->getId());
            
            // Add delay stamp if scheduling is needed
            $stamps = [];
            if ($isScheduled) {
                $stamps[] = new DelayStamp($delayMs);
                $notification->setStatus(Notification::STATUS_SCHEDULED);
            } else {
                $notification->setStatus(Notification::STATUS_QUEUED);
            }

            // Dispatch the message
            $this->messageBus->dispatch($message, $stamps);
            
            // Persist the status change
            $this->entityManager->flush();

            $this->logger->info('Notification dispatched to messenger', [
                'notification_id' => $notification->getId(),
                'delay_ms' => $delayMs,
                'scheduled' => $isScheduled,
                'status' => $notification->getStatus()
            ]);

            return [
                'success' => true,
                'scheduled' => $isScheduled,
                'delay_ms' => $delayMs,
                'status' => $notification->getStatus()
            ];

        } catch (\Exception $e) {
            $notification->setStatus(Notification::STATUS_FAILED);
            $this->entityManager->flush();
            
            $this->logger->error('Failed to send notification', [
                'notification_id' => $notification->getId(),
                'error' => $e->getMessage(),
                'exception' => $e
            ]);

            throw $e;
        }
    }

    /**
     * Send notification to specific channels only
     */
    public function sendNotificationToChannels(Notification $notification, array $channels, \DateTimeImmutable $now = null): array
    {
        $now = $now ?? new \DateTimeImmutable();
        
        if (!in_array($notification->getStatus(), [Notification::STATUS_DRAFT, Notification::STATUS_SCHEDULED, Notification::STATUS_QUEUED])) {
            throw new \InvalidArgumentException(sprintf('Cannot send notification with status "%s"', $notification->getStatus()));
        }

        if (empty($channels)) {
            throw new \InvalidArgumentException('No channels specified for sending');
        }

        try {
            $delayMs = $this->calculateNotificationDelay($notification, $now);
            $isScheduled = $delayMs > 0;

            // Create messenger message with specific channels
            $message = new SendNotificationMessage($notification->getId(), null, null, ['channels' => $channels]);
            
            // Add delay stamp if scheduling is needed
            $stamps = [];
            if ($isScheduled) {
                $stamps[] = new DelayStamp($delayMs);
                $notification->setStatus(Notification::STATUS_SCHEDULED);
            } else {
                $notification->setStatus(Notification::STATUS_QUEUED);
            }

            // Dispatch the message
            $this->messageBus->dispatch($message, $stamps);
            
            // Persist the status change
            $this->entityManager->flush();

            $this->logger->info('Notification dispatched to specific channels', [
                'notification_id' => $notification->getId(),
                'channels' => $channels,
                'delay_ms' => $delayMs,
                'scheduled' => $isScheduled
            ]);

            return [
                'success' => true,
                'scheduled' => $isScheduled,
                'delay_ms' => $delayMs,
                'channels' => $channels,
                'status' => $notification->getStatus()
            ];

        } catch (\Exception $e) {
            $notification->setStatus(Notification::STATUS_FAILED);
            $this->entityManager->flush();
            
            $this->logger->error('Failed to send notification to channels', [
                'notification_id' => $notification->getId(),
                'channels' => $channels,
                'error' => $e->getMessage(),
                'exception' => $e
            ]);

            throw $e;
        }
    }

    /**
     * Calculate delay in milliseconds for notification scheduling
     */
    private function calculateNotificationDelay(Notification $notification, \DateTimeImmutable $now): int
    {
        $scheduledAt = $notification->getScheduledAt();
        
        if ($scheduledAt === null) {
            return 0; // Send immediately
        }

        $delaySeconds = $scheduledAt->getTimestamp() - $now->getTimestamp();
        
        if ($delaySeconds <= 0) {
            return 0; // Send immediately if scheduled time has passed
        }

        // Convert to milliseconds
        return $delaySeconds * 1000;
    }
}
