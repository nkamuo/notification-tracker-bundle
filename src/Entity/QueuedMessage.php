<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'notification_queued_messages')]
#[ORM\Index(columns: ['transport', 'available_at', 'delivered_at'], name: 'idx_transport_available')]
#[ORM\Index(columns: ['transport', 'queue_name', 'priority'], name: 'idx_transport_queue_priority')]
class QueuedMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $transport;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $queueName;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(type: Types::JSON)]
    private array $headers;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $availableAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $priority = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $retryCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 3])]
    private int $maxRetries = 3;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $notificationProvider = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $campaignId = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $templateId = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = 'queued';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $processingMetadata = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->headers = [];
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTransport(): string
    {
        return $this->transport;
    }

    public function setTransport(string $transport): self
    {
        $this->transport = $transport;
        return $this;
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }

    public function setQueueName(string $queueName): self
    {
        $this->queueName = $queueName;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(?array $headers): self
    {
        $this->headers = $headers ?? [];
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAvailableAt(): ?\DateTimeImmutable
    {
        return $this->availableAt;
    }

    public function setAvailableAt(?\DateTimeImmutable $availableAt): self
    {
        $this->availableAt = $availableAt;
        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): self
    {
        $this->deliveredAt = $deliveredAt;
        return $this;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): self
    {
        $this->processedAt = $processedAt;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function setRetryCount(int $retryCount): self
    {
        $this->retryCount = $retryCount;
        return $this;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function getNotificationProvider(): ?string
    {
        return $this->notificationProvider;
    }

    public function setNotificationProvider(?string $notificationProvider): self
    {
        $this->notificationProvider = $notificationProvider;
        return $this;
    }

    public function getCampaignId(): ?string
    {
        return $this->campaignId;
    }

    public function setCampaignId(?string $campaignId): self
    {
        $this->campaignId = $campaignId;
        return $this;
    }

    public function getTemplateId(): ?string
    {
        return $this->templateId;
    }

    public function setTemplateId(?string $templateId): self
    {
        $this->templateId = $templateId;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getProcessingMetadata(): ?array
    {
        return $this->processingMetadata;
    }

    public function setProcessingMetadata(?array $processingMetadata): self
    {
        $this->processingMetadata = $processingMetadata;
        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->availableAt === null || $this->availableAt <= new \DateTimeImmutable();
    }

    public function isDelivered(): bool
    {
        return $this->deliveredAt !== null;
    }

    public function isProcessed(): bool
    {
        return $this->processedAt !== null;
    }

    public function canRetry(): bool
    {
        return $this->retryCount < $this->maxRetries;
    }

    public function markAsDelivered(): self
    {
        $this->deliveredAt = new \DateTimeImmutable();
        $this->status = 'delivered';
        return $this;
    }

    public function markAsProcessed(): self
    {
        $this->processedAt = new \DateTimeImmutable();
        $this->status = 'processed';
        return $this;
    }

    public function markAsFailed(string $errorMessage): self
    {
        $this->retryCount++;
        $this->errorMessage = $errorMessage;
        $this->status = $this->canRetry() ? 'retrying' : 'failed';
        return $this;
    }

    public function scheduleRetry(\DateTimeImmutable $availableAt): self
    {
        $this->availableAt = $availableAt;
        $this->status = 'retrying';
        return $this;
    }
}
