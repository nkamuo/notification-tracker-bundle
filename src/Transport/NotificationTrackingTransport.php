<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Transport;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\QueuedMessage;
use Nkamuo\NotificationTrackerBundle\Repository\QueuedMessageRepository;
use Nkamuo\NotificationTrackerBundle\Service\NotificationAnalyticsCollector;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTemplateStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Uid\Uuid;

class NotificationTrackingTransport implements TransportInterface, MessageCountAwareInterface, QueueReceiverInterface
{
    private string $transportName;
    private string $queueName;
    private bool $analyticsEnabled;
    private bool $providerAwareRouting;
    private int $batchSize;
    private int $maxRetries;
    private array $retryDelays;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private QueuedMessageRepository $repository,
        private SerializerInterface $serializer,
        private NotificationAnalyticsCollector $analyticsCollector,
        array $options = []
    ) {
        $this->transportName = $options['transport_name'] ?? 'default';
        $this->queueName = $options['queue_name'] ?? 'default';
        $this->analyticsEnabled = $options['analytics_enabled'] ?? true;
        $this->providerAwareRouting = $options['provider_aware_routing'] ?? false;
        $this->batchSize = $options['batch_size'] ?? 10;
        $this->maxRetries = $options['max_retries'] ?? 3;
        $this->retryDelays = $options['retry_delays'] ?? [1000, 5000, 30000]; // milliseconds
    }

    public function get(): iterable
    {
        // Get available messages (queued and ready)
        $messages = $this->repository->findAvailableMessages(
            $this->transportName,
            $this->queueName,
            $this->batchSize
        );

        // Get retry messages if available slots remain
        if (count($messages) < $this->batchSize) {
            $retryMessages = $this->repository->findRetryableMessages(
                $this->transportName,
                $this->queueName,
                $this->batchSize - count($messages)
            );
            $messages = array_merge($messages, $retryMessages);
        }

        foreach ($messages as $queuedMessage) {
            try {
                // Mark as delivered
                $queuedMessage->markAsDelivered();
                $this->entityManager->flush();

                // Decode the message
                $envelope = $this->serializer->decode([
                    'body' => $queuedMessage->getBody(),
                    'headers' => $queuedMessage->getHeaders(),
                ]);

                // Add transport message ID for acknowledgment
                $envelope = $envelope->with(new TransportMessageIdStamp($queuedMessage->getId()->toRfc4122()));

                // Track analytics if enabled
                if ($this->analyticsEnabled) {
                    $this->analyticsCollector->recordMessageDequeued($queuedMessage);
                }

                yield $envelope;
            } catch (\Throwable $e) {
                // Mark as failed and continue with next message
                $queuedMessage->markAsFailed($e->getMessage());
                $this->entityManager->flush();

                if ($this->analyticsEnabled) {
                    $this->analyticsCollector->recordMessageFailed($queuedMessage, $e);
                }
            }
        }
    }

    public function ack(Envelope $envelope): void
    {
        $stamp = $envelope->last(TransportMessageIdStamp::class);
        if (!$stamp instanceof TransportMessageIdStamp) {
            throw new TransportException('No TransportMessageIdStamp found on the Envelope.');
        }

        $queuedMessage = $this->repository->find($stamp->getId());
        if (!$queuedMessage) {
            throw new TransportException(sprintf('Queued message with ID "%s" not found.', $stamp->getId()));
        }

        try {
            // Mark as processed
            $queuedMessage->markAsProcessed();
            $this->entityManager->flush();

            // Track analytics if enabled
            if ($this->analyticsEnabled) {
                $this->analyticsCollector->recordMessageProcessed($queuedMessage);
            }
        } catch (\Throwable $e) {
            throw new TransportException(sprintf('Could not acknowledge message: %s', $e->getMessage()), 0, $e);
        }
    }

    public function reject(Envelope $envelope): void
    {
        $stamp = $envelope->last(TransportMessageIdStamp::class);
        if (!$stamp instanceof TransportMessageIdStamp) {
            throw new TransportException('No TransportMessageIdStamp found on the Envelope.');
        }

        $queuedMessage = $this->repository->find($stamp->getId());
        if (!$queuedMessage) {
            throw new TransportException(sprintf('Queued message with ID "%s" not found.', $stamp->getId()));
        }

        try {
            if ($queuedMessage->canRetry()) {
                // Schedule retry with exponential backoff
                $retryDelay = $this->calculateRetryDelay($queuedMessage->getRetryCount());
                $availableAt = new \DateTimeImmutable("+{$retryDelay} milliseconds");
                
                $queuedMessage->scheduleRetry($availableAt);
                
                if ($this->analyticsEnabled) {
                    $this->analyticsCollector->recordMessageRetried($queuedMessage);
                }
            } else {
                // Mark as permanently failed
                $queuedMessage->markAsFailed('Maximum retries exceeded');
                
                if ($this->analyticsEnabled) {
                    $this->analyticsCollector->recordMessageFailed($queuedMessage, new \Exception('Max retries exceeded'));
                }
            }

            $this->entityManager->flush();
        } catch (\Throwable $e) {
            throw new TransportException(sprintf('Could not reject message: %s', $e->getMessage()), 0, $e);
        }
    }

    public function send(Envelope $envelope): Envelope
    {
        try {
            $encodedMessage = $this->serializer->encode($envelope);
            
            $queuedMessage = new QueuedMessage();
            $queuedMessage->setTransport($this->transportName);
            $queuedMessage->setQueueName($this->getQueueNameForMessage($envelope));
            $queuedMessage->setBody($encodedMessage['body']);
            $queuedMessage->setHeaders($encodedMessage['headers'] ?? []);
            $queuedMessage->setMaxRetries($this->maxRetries);

            // Handle delay
            $delayStamp = $envelope->last(DelayStamp::class);
            if ($delayStamp instanceof DelayStamp) {
                $availableAt = new \DateTimeImmutable('+' . $delayStamp->getDelay() . ' milliseconds');
                $queuedMessage->setAvailableAt($availableAt);
            }

            // Extract notification metadata from stamps
            $this->extractNotificationMetadata($envelope, $queuedMessage);

            $this->entityManager->persist($queuedMessage);
            $this->entityManager->flush();

            // Track analytics if enabled
            if ($this->analyticsEnabled) {
                $this->analyticsCollector->recordMessageQueued($queuedMessage);
            }

            return $envelope->with(new TransportMessageIdStamp($queuedMessage->getId()->toRfc4122()));

        } catch (\Throwable $e) {
            throw new TransportException(sprintf('Could not send message: %s', $e->getMessage()), 0, $e);
        }
    }

    public function getMessageCount(): int
    {
        $stats = $this->repository->getQueueStatistics($this->transportName, $this->queueName);
        return $stats['queued'] + $stats['retrying'];
    }

    public function getFromQueues(array $queueNames): iterable
    {
        // Store original queue name
        $originalQueueName = $this->queueName;
        
        try {
            foreach ($queueNames as $queueName) {
                $this->queueName = $queueName;
                yield from $this->get();
            }
        } finally {
            // Restore original queue name
            $this->queueName = $originalQueueName;
        }
    }

    private function getQueueNameForMessage(Envelope $envelope): string
    {
        if (!$this->providerAwareRouting) {
            return $this->queueName;
        }

        // Route to provider-specific queues if enabled
        $providerStamp = $envelope->last(NotificationProviderStamp::class);
        if ($providerStamp instanceof NotificationProviderStamp) {
            return $this->queueName . '_' . $providerStamp->getProvider();
        }

        return $this->queueName;
    }

    private function extractNotificationMetadata(Envelope $envelope, QueuedMessage $queuedMessage): void
    {
        // Extract notification provider
        $providerStamp = $envelope->last(NotificationProviderStamp::class);
        if ($providerStamp instanceof NotificationProviderStamp) {
            $queuedMessage->setNotificationProvider($providerStamp->getProvider());
            $queuedMessage->setPriority($providerStamp->getPriority());
        }

        // Extract campaign information
        $campaignStamp = $envelope->last(NotificationCampaignStamp::class);
        if ($campaignStamp instanceof NotificationCampaignStamp) {
            $queuedMessage->setCampaignId($campaignStamp->getCampaignId());
        }

        // Extract template information
        $templateStamp = $envelope->last(NotificationTemplateStamp::class);
        if ($templateStamp instanceof NotificationTemplateStamp) {
            $queuedMessage->setTemplateId($templateStamp->getTemplateId());
        }
    }

    private function calculateRetryDelay(int $retryCount): int
    {
        if (isset($this->retryDelays[$retryCount])) {
            return $this->retryDelays[$retryCount];
        }

        // Default exponential backoff if no specific delay configured
        return min(30000, 1000 * (2 ** $retryCount)); // Max 30 seconds
    }
}
