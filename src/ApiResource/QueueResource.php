<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Nkamuo\NotificationTrackerBundle\Entity\QueuedMessage;
use Nkamuo\NotificationTrackerBundle\State\QueueResourceStateProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'Queue',
    operations: [
        new Get(
            uriTemplate: '/queue/messages/{id}',
            provider: QueueResourceStateProvider::class,
            normalizationContext: ['groups' => ['queue:read', 'queue:item']]
        ),
        new GetCollection(
            uriTemplate: '/queue/messages',
            provider: QueueResourceStateProvider::class,
            normalizationContext: ['groups' => ['queue:read', 'queue:list']]
        ),
        new GetCollection(
            uriTemplate: '/queue/stats',
            name: 'stats',
            provider: QueueResourceStateProvider::class,
            normalizationContext: ['groups' => ['queue:stats']]
        ),
        new GetCollection(
            uriTemplate: '/queue/health',
            name: 'health',
            provider: QueueResourceStateProvider::class,
            normalizationContext: ['groups' => ['queue:health']]
        )
    ]
)]
class QueueResource
{
    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public string $id;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public string $transport;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public string $queueName;

    #[Groups(['queue:read', 'queue:item'])]
    public string $body;

    #[Groups(['queue:read', 'queue:item'])]
    public array $headers;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public \DateTimeImmutable $createdAt;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?\DateTimeImmutable $availableAt;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?\DateTimeImmutable $deliveredAt;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?\DateTimeImmutable $processedAt;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public int $priority;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public int $retryCount;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public int $maxRetries;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?string $notificationProvider;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?string $campaignId;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?string $templateId;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public string $status;

    #[Groups(['queue:read', 'queue:item'])]
    public ?string $errorMessage;

    #[Groups(['queue:read', 'queue:item'])]
    public ?array $processingMetadata;

    // Computed properties for stats
    #[Groups(['queue:stats'])]
    public int $totalMessages;

    #[Groups(['queue:stats'])]
    public int $queuedMessages;

    #[Groups(['queue:stats'])]
    public int $deliveredMessages;

    #[Groups(['queue:stats'])]
    public int $processedMessages;

    #[Groups(['queue:stats'])]
    public int $failedMessages;

    #[Groups(['queue:stats'])]
    public int $retryingMessages;

    #[Groups(['queue:stats'])]
    public array $messagesByTransport;

    #[Groups(['queue:stats'])]
    public array $messagesByProvider;

    #[Groups(['queue:stats'])]
    public float $averageProcessingTime;

    #[Groups(['queue:stats'])]
    public float $successRate;

    // Health check properties
    #[Groups(['queue:health'])]
    public string $overallHealth;

    #[Groups(['queue:health'])]
    public array $transportHealth;

    #[Groups(['queue:health'])]
    public int $oldestQueuedMessageAge;

    #[Groups(['queue:health'])]
    public int $stuckMessagesCount;

    #[Groups(['queue:health'])]
    public array $healthChecks;

    public static function fromEntity(QueuedMessage $queuedMessage): self
    {
        $resource = new self();
        $resource->id = $queuedMessage->getId()->toRfc4122();
        $resource->transport = $queuedMessage->getTransport();
        $resource->queueName = $queuedMessage->getQueueName();
        $resource->body = $queuedMessage->getBody();
        $resource->headers = $queuedMessage->getHeaders();
        $resource->createdAt = $queuedMessage->getCreatedAt();
        $resource->availableAt = $queuedMessage->getAvailableAt();
        $resource->deliveredAt = $queuedMessage->getDeliveredAt();
        $resource->processedAt = $queuedMessage->getProcessedAt();
        $resource->priority = $queuedMessage->getPriority();
        $resource->retryCount = $queuedMessage->getRetryCount();
        $resource->maxRetries = $queuedMessage->getMaxRetries();
        $resource->notificationProvider = $queuedMessage->getNotificationProvider();
        $resource->campaignId = $queuedMessage->getCampaignId();
        $resource->templateId = $queuedMessage->getTemplateId();
        $resource->status = $queuedMessage->getStatus();
        $resource->errorMessage = $queuedMessage->getErrorMessage();
        $resource->processingMetadata = $queuedMessage->getProcessingMetadata();

        return $resource;
    }

    public static function createStatsResource(array $stats): self
    {
        $resource = new self();
        $resource->totalMessages = $stats['total_messages'];
        $resource->queuedMessages = $stats['queued_messages'];
        $resource->deliveredMessages = $stats['delivered_messages'];
        $resource->processedMessages = $stats['processed_messages'];
        $resource->failedMessages = $stats['failed_messages'];
        $resource->retryingMessages = $stats['retrying_messages'];
        $resource->messagesByTransport = $stats['messages_by_transport'];
        $resource->messagesByProvider = $stats['messages_by_provider'];
        $resource->averageProcessingTime = $stats['average_processing_time'];
        $resource->successRate = $stats['success_rate'];

        return $resource;
    }

    public static function createHealthResource(array $health): self
    {
        $resource = new self();
        $resource->overallHealth = $health['overall_health'];
        $resource->transportHealth = $health['transport_health'];
        $resource->oldestQueuedMessageAge = $health['oldest_queued_message_age'];
        $resource->stuckMessagesCount = $health['stuck_messages_count'];
        $resource->healthChecks = $health['health_checks'];

        return $resource;
    }
}
