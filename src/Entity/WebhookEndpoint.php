<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'nt_webhook_endpoint')]
#[ORM\Index(name: 'idx_nt_webhook_provider', columns: ['provider_name'])]
#[ORM\Index(name: 'idx_nt_webhook_enabled', columns: ['enabled'])]
#[UniqueEntity(fields: ['name'], message: 'A webhook endpoint with this name already exists.')]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['webhook_endpoint:read']]),
        new GetCollection(normalizationContext: ['groups' => ['webhook_endpoint:read', 'webhook_endpoint:list']]),
        new Post(
            normalizationContext: ['groups' => ['webhook_endpoint:read']],
            denormalizationContext: ['groups' => ['webhook_endpoint:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['webhook_endpoint:read']],
            denormalizationContext: ['groups' => ['webhook_endpoint:write']]
        ),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['webhook_endpoint:read']],
    denormalizationContext: ['groups' => ['webhook_endpoint:write']]
)]
class WebhookEndpoint
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[Groups(['webhook_endpoint:read', 'webhook_endpoint:list'])]
    private Ulid $id;

    #[ORM\Column(length: 100, unique: true)]
    #[Groups(['webhook_endpoint:read', 'webhook_endpoint:write', 'webhook_endpoint:list'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    private string $name;

    #[ORM\Column(length: 50)]
    #[Groups(['webhook_endpoint:read', 'webhook_endpoint:write', 'webhook_endpoint:list'])]
    #[Assert\NotBlank]
    private string $providerName;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['webhook_endpoint:read', 'webhook_endpoint:write'])]
    private array $configuration = [];

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['webhook_endpoint:read', 'webhook_endpoint:write', 'webhook_endpoint:list'])]
    private bool $enabled = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['webhook_endpoint:read', 'webhook_endpoint:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['webhook_endpoint:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['webhook_endpoint:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['webhook_endpoint:read'])]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['webhook_endpoint:read'])]
    private int $totalRequests = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['webhook_endpoint:read'])]
    private int $successfulRequests = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['webhook_endpoint:read'])]
    private int $failedRequests = 0;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function setProviderName(string $providerName): self
    {
        $this->providerName = $providerName;
        return $this;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function setConfiguration(array $configuration): self
    {
        $this->configuration = $configuration;
        return $this;
    }

    public function getConfigurationValue(string $key, mixed $default = null): mixed
    {
        return $this->configuration[$key] ?? $default;
    }

    public function setConfigurationValue(string $key, mixed $value): self
    {
        $this->configuration[$key] = $value;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    public function getTotalRequests(): int
    {
        return $this->totalRequests;
    }

    public function incrementTotalRequests(): self
    {
        $this->totalRequests++;
        return $this;
    }

    public function getSuccessfulRequests(): int
    {
        return $this->successfulRequests;
    }

    public function incrementSuccessfulRequests(): self
    {
        $this->successfulRequests++;
        return $this;
    }

    public function getFailedRequests(): int
    {
        return $this->failedRequests;
    }

    public function incrementFailedRequests(): self
    {
        $this->failedRequests++;
        return $this;
    }

    #[Groups(['webhook_endpoint:read'])]
    public function getSuccessRate(): float
    {
        if ($this->totalRequests === 0) {
            return 0.0;
        }

        return round(($this->successfulRequests / $this->totalRequests) * 100, 2);
    }

    /**
     * Generate the webhook URL for this endpoint
     */
    #[Groups(['webhook_endpoint:read'])]
    public function getWebhookUrl(): string
    {
        return '/webhooks/notification-tracker/' . $this->providerName . '/' . $this->id;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
