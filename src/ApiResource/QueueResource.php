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
        ],
    routePrefix: '/notification-tracker'
)]
class QueueResource
{
    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?string $id = null;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?string $transport = null;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?string $queueName = null;

    #[Groups(['queue:read', 'queue:item'])]
    public ?string $body = null;

    #[Groups(['queue:read', 'queue:item'])]
    public ?array $headers = null;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?\DateTimeImmutable $createdAt = null;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?\DateTimeImmutable $availableAt = null;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?\DateTimeImmutable $deliveredAt = null;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?\DateTimeImmutable $processedAt = null;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?int $priority = null;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?int $retryCount = null;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?int $maxRetries = null;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?string $notificationProvider = null;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?string $campaignId = null;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?string $templateId = null;

    #[Groups(['queue:read', 'queue:list', 'queue:item'])]
    public ?string $status = null;

    #[Groups(['queue:read', 'queue:item'])]
    public ?string $errorMessage = null;

    #[Groups(['queue:read', 'queue:item'])]
    public ?array $processingMetadata = null;

    // Computed properties for stats
    #[Groups(['queue:stats'])]
    public int $totalMessages = 0;

    #[Groups(['queue:stats'])]
    public int $queuedMessages = 0;

    #[Groups(['queue:stats'])]
    public int $deliveredMessages = 0;

    #[Groups(['queue:stats'])]
    public int $processedMessages = 0;

    #[Groups(['queue:stats'])]
    public int $failedMessages = 0;

    #[Groups(['queue:stats'])]
    public int $retryingMessages = 0;

    #[Groups(['queue:stats'])]
    public array $messagesByTransport = [];

    #[Groups(['queue:stats'])]
    public array $messagesByProvider = [];

    #[Groups(['queue:stats'])]
    public float $averageProcessingTime = 0.0;

    #[Groups(['queue:stats'])]
    public float $successRate = 0.0;

    // Health check properties
    #[Groups(['queue:health'])]
    public string $overallHealth = '';

    #[Groups(['queue:health'])]
    public array $transportHealth = [];

    #[Groups(['queue:health'])]
    public int $oldestQueuedMessageAge = 0;

    #[Groups(['queue:health'])]
    public int $stuckMessagesCount = 0;

    #[Groups(['queue:health'])]
    public array $healthChecks = [];

    public function __construct(?string $id = null)
    {
        // Always ensure we have an ID
        $this->id = $id ?? self::generateId('message');
    }

    public static function fromEntity(QueuedMessage $queuedMessage): self
    {
        $resource = new self();
        // Always ensure we have an ID - use the entity ID or generate one
        $resource->id = $queuedMessage->getId()?->toRfc4122() ?? self::generateId('message');
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
        // Generate a deterministic ID for stats based on current state
        $resource->id = $stats['id'] ?? self::generateId('stats', $stats);
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
        // Generate a deterministic ID for health based on current state
        $resource->id = self::generateId('health', $health);
        $resource->overallHealth = $health['overall_health'];
        $resource->transportHealth = $health['transport_health'];
        $resource->oldestQueuedMessageAge = $health['oldest_queued_message_age'];
        $resource->stuckMessagesCount = $health['stuck_messages_count'];
        $resource->healthChecks = $health['health_checks'];

        return $resource;
    }

    /**
     * Generate a consistent ID for queue resources
     * 
     * @param string $type The type of resource (message, stats, health)
     * @param array|null $data Optional data to make ID deterministic
     * @return string
     */
    private static function generateId(string $type, ?array $data = null): string
    {
        switch ($type) {
            case 'stats':
                // Create deterministic ID based on current timestamp (rounded to minute)
                // This allows consistent IDs for stats within the same minute
                $minute = floor(time() / 60);
                return "stats-{$minute}";
                
            case 'health':
                // Create deterministic ID based on current timestamp (rounded to 30 seconds)
                // This allows consistent IDs for health checks within 30-second windows
                $window = floor(time() / 30);
                return "health-{$window}";
                
            case 'message':
            default:
                // For individual messages, generate a unique UUID-like ID
                return 'queue-' . bin2hex(random_bytes(16));
        }
    }

    /**
     * Get a human-readable identifier for this queue resource
     * Useful for logging, debugging, and user interfaces
     */
    public function getDisplayId(): string
    {
        if ($this->isStatsResource()) {
            return "Stats for " . date('Y-m-d H:i', (int)substr($this->id, 6) * 60);
        }
        
        if ($this->isHealthResource()) {
            return "Health check at " . date('Y-m-d H:i:s', (int)substr($this->id, 7) * 30);
        }
        
        // For message resources, show transport + queue info if available
        $parts = [];
        if ($this->transport) {
            $parts[] = "Transport: {$this->transport}";
        }
        if ($this->queueName) {
            $parts[] = "Queue: {$this->queueName}";
        }
        if ($this->notificationProvider) {
            $parts[] = "Provider: {$this->notificationProvider}";
        }
        
        return empty($parts) ? "Message {$this->id}" : implode(' | ', $parts);
    }

    /**
     * Check if this is a stats resource
     */
    public function isStatsResource(): bool
    {
        return str_starts_with($this->id, 'stats-');
    }

    /**
     * Check if this is a health resource
     */
    public function isHealthResource(): bool
    {
        return str_starts_with($this->id, 'health-');
    }

    /**
     * Check if this is a message resource
     */
    public function isMessageResource(): bool
    {
        return str_starts_with($this->id, 'queue-') || 
               (!$this->isStatsResource() && !$this->isHealthResource());
    }
}
