<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\OpenApi\Model\Operation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'nt_notification_draft')]
#[ORM\Index(name: 'idx_nt_draft_status', columns: ['status'])]
#[ORM\Index(name: 'idx_nt_draft_scheduled', columns: ['scheduled_at'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['draft:read']]),
        new GetCollection(normalizationContext: ['groups' => ['draft:read', 'draft:list']]),
        new Post(
            normalizationContext: ['groups' => ['draft:read']],
            denormalizationContext: ['groups' => ['draft:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['draft:read']],
            denormalizationContext: ['groups' => ['draft:write']]
        ),
        new Delete(),
        new Post(
            uriTemplate: '/notification_drafts/{id}/send',
            controller: 'Nkamuo\NotificationTrackerBundle\Controller\Api\SendDraftController',
            normalizationContext: ['groups' => ['draft:read']],
            openapi: new Operation(
                summary: 'Send a notification draft immediately',
                description: 'Sends a draft notification to all configured recipients and channels'
            )
        ),
        new Put(
            uriTemplate: '/notification_drafts/{id}/schedule',
            controller: 'Nkamuo\NotificationTrackerBundle\Controller\Api\ScheduleDraftController',
            normalizationContext: ['groups' => ['draft:read']],
            denormalizationContext: ['groups' => ['draft:schedule']],
            openapi: new Operation(
                summary: 'Schedule a notification draft',
                description: 'Schedule a draft notification to be sent at a specified time'
            )
        ),
    ],
    normalizationContext: ['groups' => ['draft:read']],
    denormalizationContext: ['groups' => ['draft:write']]
)]
class NotificationDraft
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const ALLOWED_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SCHEDULED,
        self::STATUS_SENDING,
        self::STATUS_SENT,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[Groups(['draft:read', 'draft:list'])]
    private Ulid $id;

    #[ORM\Column(length: 200)]
    #[Groups(['draft:read', 'draft:write', 'draft:list'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 200)]
    private string $subject;

    #[ORM\Column(length: 50)]
    #[Groups(['draft:read', 'draft:write', 'draft:list'])]
    #[Assert\Choice(choices: self::ALLOWED_STATUSES)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['draft:read', 'draft:write'])]
    private array $channels = [];

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['draft:read', 'draft:write'])]
    private array $recipients = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['draft:read', 'draft:write'])]
    private ?string $textContent = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['draft:read', 'draft:write'])]
    private ?string $htmlContent = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['draft:read', 'draft:write'])]
    private array $metadata = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['draft:read', 'draft:write', 'draft:list'])]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['draft:read', 'draft:list'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['draft:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['draft:read'])]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['draft:read'])]
    private ?string $failureReason = null;

    #[ORM\ManyToMany(targetEntity: Label::class)]
    #[ORM\JoinTable(name: 'nt_draft_labels')]
    #[Groups(['draft:read', 'draft:write', 'draft:list'])]
    private Collection $labels;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'draft')]
    #[Groups(['draft:read'])]
    private Collection $messages;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->labels = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
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

    public function getChannels(): array
    {
        return $this->channels;
    }

    public function setChannels(array $channels): self
    {
        $this->channels = $channels;
        return $this;
    }

    public function addChannel(string $channel): self
    {
        if (!in_array($channel, $this->channels)) {
            $this->channels[] = $channel;
        }
        return $this;
    }

    public function removeChannel(string $channel): self
    {
        $this->channels = array_filter($this->channels, fn($c) => $c !== $channel);
        return $this;
    }

    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function setRecipients(array $recipients): self
    {
        $this->recipients = $recipients;
        return $this;
    }

    public function addRecipient(array $recipient): self
    {
        $this->recipients[] = $recipient;
        return $this;
    }

    public function getTextContent(): ?string
    {
        return $this->textContent;
    }

    public function setTextContent(?string $textContent): self
    {
        $this->textContent = $textContent;
        return $this;
    }

    public function getHtmlContent(): ?string
    {
        return $this->htmlContent;
    }

    public function setHtmlContent(?string $htmlContent): self
    {
        $this->htmlContent = $htmlContent;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;
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

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): self
    {
        $this->failureReason = $failureReason;
        return $this;
    }

    /**
     * @return Collection<int, Label>
     */
    public function getLabels(): Collection
    {
        return $this->labels;
    }

    public function addLabel(Label $label): self
    {
        if (!$this->labels->contains($label)) {
            $this->labels->add($label);
        }
        return $this;
    }

    public function removeLabel(Label $label): self
    {
        $this->labels->removeElement($label);
        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setDraft($this);
        }
        return $this;
    }

    public function removeMessage(Message $message): self
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getDraft() === $this) {
                $message->setDraft(null);
            }
        }
        return $this;
    }

    #[Groups(['draft:read', 'draft:list'])]
    public function getChannelSummary(): array
    {
        return [
            'total' => count($this->channels),
            'channels' => $this->channels,
        ];
    }

    #[Groups(['draft:read', 'draft:list'])]
    public function getRecipientSummary(): array
    {
        $byChannel = [];
        foreach ($this->recipients as $recipient) {
            $channel = $recipient['channel'] ?? 'unknown';
            $byChannel[$channel] = ($byChannel[$channel] ?? 0) + 1;
        }

        return [
            'total' => count($this->recipients),
            'by_channel' => $byChannel,
        ];
    }

    #[Groups(['draft:read'])]
    public function getMessagesSummary(): array
    {
        $statusCounts = [];
        foreach ($this->messages as $message) {
            $status = $message->getStatus();
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }

        return [
            'total' => $this->messages->count(),
            'by_status' => $statusCounts,
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->subject;
    }
}
