<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\QueuedMessage;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Psr\Log\LoggerInterface;

class NotificationAnalyticsCollector
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageTracker $messageTracker,
        private LoggerInterface $logger
    ) {}

    public function recordMessageQueued(QueuedMessage $queuedMessage): void
    {
        try {
            // Update processing metadata
            $metadata = $queuedMessage->getProcessingMetadata() ?? [];
            $metadata['queued_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
            $metadata['queue_depth'] = $this->getQueueDepth($queuedMessage->getTransport(), $queuedMessage->getQueueName());
            $queuedMessage->setProcessingMetadata($metadata);

            $this->entityManager->flush();

            $this->logger->info('Message queued for notification tracking', [
                'message_id' => $queuedMessage->getId()->toRfc4122(),
                'transport' => $queuedMessage->getTransport(),
                'queue' => $queuedMessage->getQueueName(),
                'provider' => $queuedMessage->getNotificationProvider(),
                'campaign' => $queuedMessage->getCampaignId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to record queued message analytics', [
                'message_id' => $queuedMessage->getId()->toRfc4122(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function recordMessageDequeued(QueuedMessage $queuedMessage): void
    {
        try {
            $metadata = $queuedMessage->getProcessingMetadata() ?? [];
            $metadata['dequeued_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
            
            // Calculate queue wait time
            if (isset($metadata['queued_at'])) {
                $queuedAt = new \DateTimeImmutable($metadata['queued_at']);
                $waitTime = (new \DateTimeImmutable())->getTimestamp() - $queuedAt->getTimestamp();
                $metadata['queue_wait_time_seconds'] = $waitTime;
            }

            $queuedMessage->setProcessingMetadata($metadata);
            $this->entityManager->flush();

            $this->logger->debug('Message dequeued for processing', [
                'message_id' => $queuedMessage->getId()->toRfc4122(),
                'wait_time' => $metadata['queue_wait_time_seconds'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to record dequeued message analytics', [
                'message_id' => $queuedMessage->getId()->toRfc4122(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function recordMessageProcessed(QueuedMessage $queuedMessage): void
    {
        try {
            $metadata = $queuedMessage->getProcessingMetadata() ?? [];
            $metadata['processed_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
            
            // Calculate total processing time
            if (isset($metadata['dequeued_at'])) {
                $dequeuedAt = new \DateTimeImmutable($metadata['dequeued_at']);
                $processingTime = (new \DateTimeImmutable())->getTimestamp() - $dequeuedAt->getTimestamp();
                $metadata['processing_time_seconds'] = $processingTime;
            }

            $queuedMessage->setProcessingMetadata($metadata);
            $this->entityManager->flush();

            // Create analytics event for main tracking system
            $this->createAnalyticsEvent($queuedMessage, 'message_processed');

            $this->logger->info('Message processed successfully', [
                'message_id' => $queuedMessage->getId()->toRfc4122(),
                'processing_time' => $metadata['processing_time_seconds'] ?? null,
                'total_time' => isset($metadata['queued_at']) 
                    ? (new \DateTimeImmutable())->getTimestamp() - (new \DateTimeImmutable($metadata['queued_at']))->getTimestamp()
                    : null,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to record processed message analytics', [
                'message_id' => $queuedMessage->getId()->toRfc4122(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function recordMessageFailed(QueuedMessage $queuedMessage, \Throwable $exception): void
    {
        try {
            $metadata = $queuedMessage->getProcessingMetadata() ?? [];
            $metadata['failed_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
            $metadata['failure_reason'] = $exception->getMessage();
            $metadata['failure_type'] = get_class($exception);
            
            if (isset($metadata['dequeued_at'])) {
                $dequeuedAt = new \DateTimeImmutable($metadata['dequeued_at']);
                $processingTime = (new \DateTimeImmutable())->getTimestamp() - $dequeuedAt->getTimestamp();
                $metadata['processing_time_before_failure_seconds'] = $processingTime;
            }

            $queuedMessage->setProcessingMetadata($metadata);
            $this->entityManager->flush();

            // Create analytics event for main tracking system
            $this->createAnalyticsEvent($queuedMessage, 'message_failed', [
                'error_type' => get_class($exception),
                'error_message' => $exception->getMessage(),
            ]);

            $this->logger->warning('Message processing failed', [
                'message_id' => $queuedMessage->getId()->toRfc4122(),
                'retry_count' => $queuedMessage->getRetryCount(),
                'can_retry' => $queuedMessage->canRetry(),
                'error' => $exception->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to record failed message analytics', [
                'message_id' => $queuedMessage->getId()->toRfc4122(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function recordMessageRetried(QueuedMessage $queuedMessage): void
    {
        try {
            $metadata = $queuedMessage->getProcessingMetadata() ?? [];
            $metadata['retried_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
            $metadata['retry_count'] = $queuedMessage->getRetryCount();

            $queuedMessage->setProcessingMetadata($metadata);
            $this->entityManager->flush();

            // Create analytics event for main tracking system
            $this->createAnalyticsEvent($queuedMessage, 'message_retried', [
                'retry_count' => $queuedMessage->getRetryCount(),
                'available_at' => $queuedMessage->getAvailableAt()?->format(\DateTimeInterface::ATOM),
            ]);

            $this->logger->info('Message scheduled for retry', [
                'message_id' => $queuedMessage->getId()->toRfc4122(),
                'retry_count' => $queuedMessage->getRetryCount(),
                'available_at' => $queuedMessage->getAvailableAt()?->format(\DateTimeInterface::ATOM),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to record retried message analytics', [
                'message_id' => $queuedMessage->getId()->toRfc4122(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getQueueDepth(string $transport, string $queueName): int
    {
        try {
            $query = $this->entityManager->createQuery(
                'SELECT COUNT(qm.id) FROM ' . QueuedMessage::class . ' qm 
                WHERE qm.transport = :transport 
                AND qm.queueName = :queueName 
                AND qm.status IN (:statuses)'
            );
            
            $query->setParameters([
                'transport' => $transport,
                'queueName' => $queueName,
                'statuses' => ['queued', 'retrying']
            ]);

            return (int) $query->getSingleScalarResult();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to calculate queue depth', [
                'transport' => $transport,
                'queue' => $queueName,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    private function createAnalyticsEvent(QueuedMessage $queuedMessage, string $eventType, array $additionalData = []): void
    {
        try {
            // This integrates with the main notification tracking system
            $eventData = [
                'event_type' => $eventType,
                'transport' => $queuedMessage->getTransport(),
                'queue_name' => $queuedMessage->getQueueName(),
                'provider' => $queuedMessage->getNotificationProvider(),
                'campaign_id' => $queuedMessage->getCampaignId(),
                'template_id' => $queuedMessage->getTemplateId(),
                'priority' => $queuedMessage->getPriority(),
                'retry_count' => $queuedMessage->getRetryCount(),
                'status' => $queuedMessage->getStatus(),
                'processing_metadata' => $queuedMessage->getProcessingMetadata(),
                ...$additionalData
            ];

            // Log analytics event for integration with main tracking system
            // For now, we log the event - this could be enhanced to create
            // actual MessageEvent entities when linked to specific messages
            $this->logger->info('Queue analytics event', [
                'event_type' => $eventType,
                'message_id' => $queuedMessage->getId()->toRfc4122(),
                'data' => $eventData
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create analytics event', [
                'message_id' => $queuedMessage->getId()->toRfc4122(),
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
