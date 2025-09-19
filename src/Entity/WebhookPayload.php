<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Repository\WebhookPayloadRepository;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: WebhookPayloadRepository::class)]
#[ORM\Table(name: 'notification_tracker_webhook_payloads')]
#[ORM\Index(name: 'idx_nt_webhook_provider_processed', columns: ['provider', 'processed'])]
class WebhookPayload
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 50)]
    private string $provider;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $eventType = null;

    #[ORM\Column(type: Types::JSON)]
    private array $rawPayload = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $signature = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $processed = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $processingError = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(?string $eventType): self
    {
        $this->eventType = $eventType;
        return $this;
    }

    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    public function setRawPayload(array $rawPayload): self
    {
        $this->rawPayload = $rawPayload;
        return $this;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function setSignature(?string $signature): self
    {
        $this->signature = $signature;
        return $this;
    }

    public function isProcessed(): bool
    {
        return $this->processed;
    }

    public function setProcessed(bool $processed): self
    {
        $this->processed = $processed;
        if ($processed && !$this->processedAt) {
            $this->processedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getProcessingError(): ?string
    {
        return $this->processingError;
    }

    public function setProcessingError(?string $processingError): self
    {
        $this->processingError = $processingError;
        return $this;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }
}