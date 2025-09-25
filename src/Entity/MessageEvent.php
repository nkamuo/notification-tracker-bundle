<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Repository\MessageEventRepository;
use Nkamuo\NotificationTrackerBundle\Config\ApiRoutes;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: MessageEventRepository::class)]
#[ORM\Table(name: 'notification_tracker_message_events')]
#[ORM\Index(name: 'idx_nt_event_message', columns: ['message_id'])]
#[ORM\Index(name: 'idx_nt_event_type_date', columns: ['event_type', 'occurred_at'])]
#[ApiResource(
    shortName: 'MessageEvent',
    description: 'Message lifecycle events',
    operations: [
        new GetCollection(
            uriTemplate: ApiRoutes::MESSAGES . '/{message}/events',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            normalizationContext: ['groups' => ['event:read']],
            uriVariables: [
                'message' => new Link(
                    fromProperty: 'events',
                    fromClass: Message::class
                ),
            ],
        ),
    ]
)]
#[ApiResource(
    uriTemplate: ApiRoutes::MESSAGES . '/{message}/events/{id}',
    operations: [
        new Get(),
    ],
    uriVariables: [
        'message' => new Link(
            fromProperty: 'events',
            fromClass: Message::class
        ),
        'id' => new Link(
            fromClass: self::class,
        ),
    ],
)]
class MessageEvent
{
    public const TYPE_QUEUED = 'queued';
    public const TYPE_SENT = 'sent';
    public const TYPE_DELIVERED = 'delivered';
    public const TYPE_OPENED = 'opened';
    public const TYPE_CLICKED = 'clicked';
    public const TYPE_BOUNCED = 'bounced';
    public const TYPE_COMPLAINED = 'complained';
    public const TYPE_UNSUBSCRIBED = 'unsubscribed';
    public const TYPE_FAILED = 'failed';
    public const TYPE_RETRIED = 'retried';

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[Groups(['message:read', 'event:read'])]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Message::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Message $message;

    #[ORM\ManyToOne(targetEntity: MessageRecipient::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MessageRecipient $recipient = null;

    #[ORM\Column(length: 50)]
    #[Groups(['message:read', 'event:read'])]
    private string $eventType;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['message:read', 'event:read'])]
    private array $eventData = [];

    #[ORM\Column(length: 45, nullable: true)]
    #[Groups(['event:read'])]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['event:read'])]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['message:read', 'event:read'])]
    private \DateTimeImmutable $occurredAt;

    #[ORM\ManyToOne(targetEntity: WebhookPayload::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?WebhookPayload $webhookPayload = null;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function setMessage(Message $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getRecipient(): ?MessageRecipient
    {
        return $this->recipient;
    }

    public function setRecipient(?MessageRecipient $recipient): self
    {
        $this->recipient = $recipient;
        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;
        return $this;
    }

    public function getEventData(): array
    {
        return $this->eventData;
    }

    public function setEventData(array $eventData): self
    {
        $this->eventData = $eventData;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): self
    {
        $this->occurredAt = $occurredAt;
        return $this;
    }

    public function getWebhookPayload(): ?WebhookPayload
    {
        return $this->webhookPayload;
    }

    public function setWebhookPayload(?WebhookPayload $webhookPayload): self
    {
        $this->webhookPayload = $webhookPayload;
        return $this;
    }


    public function isQueued(): bool
    {
        return $this->eventType === self::TYPE_QUEUED;
    }

    /**
     * Alias for getEventData() to maintain compatibility
     */
    public function getMetadata(): array
    {
        return $this->eventData;
    }
}
