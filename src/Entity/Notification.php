<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Repository\NotificationRepository;
use Nkamuo\NotificationTrackerBundle\Config\ApiRoutes;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification_tracker_notifications')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Notification',
    description: 'Notification that can generate multiple messages',
    normalizationContext: ['groups' => ['notification:read']],
    denormalizationContext: ['groups' => ['notification:write']],
    operations: [
        new GetCollection(
            uriTemplate: ApiRoutes::NOTIFICATIONS,
            normalizationContext: ['groups' => ['notification:list']],
            paginationItemsPerPage: 20,
            paginationMaximumItemsPerPage: 100,
            paginationPartial: true
        ),
        new Get(
            uriTemplate: ApiRoutes::NOTIFICATIONS . '/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            normalizationContext: ['groups' => ['notification:detail']]
        ),
        new Post(
            uriTemplate: ApiRoutes::NOTIFICATIONS,
            denormalizationContext: ['groups' => ['notification:create']],
            normalizationContext: ['groups' => ['notification:detail']],
            processor: 'Nkamuo\NotificationTrackerBundle\State\NotificationCreateProcessor'
        ),
        new Put(
            uriTemplate: ApiRoutes::NOTIFICATIONS . '/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            denormalizationContext: ['groups' => ['notification:write']],
            normalizationContext: ['groups' => ['notification:detail']]
        ),
        new Post(
            uriTemplate: ApiRoutes::NOTIFICATIONS . '/{id}/send',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            controller: 'Nkamuo\NotificationTrackerBundle\Controller\Api\SendNotificationController',
            normalizationContext: ['groups' => ['notification:read']],
            openapiContext: [
                'summary' => 'Send a notification immediately',
                'description' => 'Sends a notification to all configured recipients and channels',
            ]
        ),
        new Put(
            uriTemplate: ApiRoutes::NOTIFICATIONS . '/{id}/schedule',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            controller: 'Nkamuo\NotificationTrackerBundle\Controller\Api\ScheduleNotificationController',
            normalizationContext: ['groups' => ['notification:read']],
            denormalizationContext: ['groups' => ['notification:schedule']],
            openapiContext: [
                'summary' => 'Schedule a notification',
                'description' => 'Schedule a notification to be sent at a specified time',
            ]
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact', 'importance' => 'exact', 'subject' => 'partial'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'type', 'importance', 'subject'])]
class Notification
{
    public const IMPORTANCE_LOW = 'low';
    public const IMPORTANCE_NORMAL = 'normal';
    public const IMPORTANCE_HIGH = 'high';
    public const IMPORTANCE_URGENT = 'urgent';

    // Status constants for workflow management
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    // Direction constants for message/notification flow type
    public const DIRECTION_INBOUND = 'inbound';   // Received notifications/messages
    public const DIRECTION_OUTBOUND = 'outbound'; // Sent notifications/messages  
    public const DIRECTION_DRAFT = 'draft';       // Draft notifications (not yet sent)

    public const ALLOWED_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SCHEDULED,
        self::STATUS_QUEUED,
        self::STATUS_SENDING,
        self::STATUS_SENT,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    public const ALLOWED_DIRECTIONS = [
        self::DIRECTION_INBOUND,
        self::DIRECTION_OUTBOUND,
        self::DIRECTION_DRAFT,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[Groups(['notification:read', 'notification:list', 'message:read'])]
    private Ulid $id;

    #[ORM\Column(length: 100)]
    #[Groups(['notification:read', 'notification:list', 'notification:write', 'notification:create'])]
    private string $type;

    #[ORM\Column(length: 20)]
    #[Groups(['notification:read', 'notification:list', 'notification:write', 'notification:create'])]
    private string $importance = self::IMPORTANCE_NORMAL;

    #[ORM\Column(length: 20)]
    #[Groups(['notification:read', 'notification:list', 'notification:write', 'notification:create'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(length: 20)]
    #[Groups(['notification:read', 'notification:list', 'notification:write', 'notification:create'])]
    private string $direction = self::DIRECTION_DRAFT;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['notification:read', 'notification:write', 'notification:list', 'notification:detail', 'notification:create'])]
    private array $channels = [];

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['notification:read', 'notification:write', 'notification:detail', 'notification:create'])]
    private array $context = [];

    #[ORM\Column(type: 'ulid', nullable: true)]
    #[Groups(['notification:read', 'notification:write', 'notification:list', 'notification:detail', 'notification:create'])]
    private ?Ulid $userId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['notification:read', 'notification:write', 'notification:list', 'notification:detail', 'notification:create'])]
    private ?string $subject = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['notification:create', 'notification:detail'])]
    private ?array $recipients = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['notification:create', 'notification:detail'])]
    private ?string $content = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['notification:create', 'notification:detail'])]
    private ?array $channelSettings = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['notification:read', 'notification:write', 'notification:detail', 'notification:create'])]
    private array $metadata = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['notification:read', 'notification:list'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['notification:read', 'notification:write', 'notification:list', 'notification:create'])]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['notification:read', 'notification:list'])]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'notification', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['notification:detail'])]
    private Collection $messages;

    #[ORM\ManyToMany(targetEntity: Label::class, inversedBy: 'notifications')]
    #[ORM\JoinTable(name: 'nt_notification_labels')]
    #[Groups(['notification:read', 'notification:write', 'notification:list'])]
    private Collection $labels;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->messages = new ArrayCollection();
        $this->labels = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getImportance(): string
    {
        return $this->importance;
    }

    public function setImportance(string $importance): self
    {
        $this->importance = $importance;
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

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function getUserId(): ?Ulid
    {
        return $this->userId;
    }

    public function setUserId(?Ulid $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getRecipients(): ?array
    {
        return $this->recipients;
    }

    public function setRecipients(?array $recipients): self
    {
        $this->recipients = $recipients;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getChannelSettings(): ?array
    {
        return $this->channelSettings;
    }

    public function setChannelSettings(?array $channelSettings): self
    {
        $this->channelSettings = $channelSettings;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setNotification($this);
        }
        return $this;
    }

    public function removeMessage(Message $message): self
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getNotification() === $this) {
                $message->setNotification(null);
            }
        }
        return $this;
    }

    /**
     * Get total message count
     */
    #[Groups(['notification:list', 'notification:detail'])]
    public function getTotalMessages(): int
    {
        return $this->messages->count();
    }

    /**
     * Get message statistics
     */
    #[Groups(['notification:list', 'notification:detail'])]
    public function getMessageStats(): array
    {
        $stats = [
            'total' => 0,
            'sent' => 0,
            'delivered' => 0,
            'failed' => 0,
            'pending' => 0,
            'queued' => 0,
            'cancelled' => 0,
        ];

        foreach ($this->messages as $message) {
            $stats['total']++;
            $status = $message->getStatus();
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $stats;
    }

    /**
     * Get recipient statistics
     */
    #[Groups(['notification:detail'])]
    public function getRecipientStats(): array
    {
        $stats = [
            'total_recipients' => 0,
            'unique_recipients' => 0,
            'total_opens' => 0,
            'total_clicks' => 0,
            'opened_recipients' => 0,
            'clicked_recipients' => 0,
        ];

        $uniqueAddresses = [];
        foreach ($this->messages as $message) {
            foreach ($message->getRecipients() as $recipient) {
                $stats['total_recipients']++;
                $uniqueAddresses[$recipient->getAddress()] = true;
                
                if ($recipient->getOpenedAt()) {
                    $stats['opened_recipients']++;
                }
                if ($recipient->getClickedAt()) {
                    $stats['clicked_recipients']++;
                }
                
                $stats['total_opens'] += $recipient->getOpenCount();
                $stats['total_clicks'] += $recipient->getClickCount();
            }
        }

        $stats['unique_recipients'] = count($uniqueAddresses);

        return $stats;
    }

    /**
     * Get engagement rates
     */
    #[Groups(['notification:list', 'notification:detail'])]
    public function getEngagementRates(): array
    {
        $recipientStats = $this->getRecipientStats();
        $messageStats = $this->getMessageStats();
        
        $delivered = $messageStats['delivered'] + $messageStats['sent'];
        $openedRecipients = $recipientStats['opened_recipients'];
        $clickedRecipients = $recipientStats['clicked_recipients'];
        
        return [
            'delivery_rate' => $messageStats['total'] > 0 ? round(($delivered / $messageStats['total']) * 100, 2) : 0,
            'open_rate' => $delivered > 0 ? round(($openedRecipients / $delivered) * 100, 2) : 0,
            'click_rate' => $openedRecipients > 0 ? round(($clickedRecipients / $openedRecipients) * 100, 2) : 0,
            'click_through_rate' => $delivered > 0 ? round(($clickedRecipients / $delivered) * 100, 2) : 0,
        ];
    }

    /**
     * Get the latest message date
     */
    #[Groups(['notification:list', 'notification:detail'])]
    public function getLatestMessageDate(): ?\DateTimeImmutable
    {
        $latest = null;
        foreach ($this->messages as $message) {
            if ($latest === null || $message->getCreatedAt() > $latest) {
                $latest = $message->getCreatedAt();
            }
        }
        return $latest;
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

    public function hasLabel(Label $label): bool
    {
        return $this->labels->contains($label);
    }

    public function hasLabelByName(string $name): bool
    {
        foreach ($this->labels as $label) {
            if ($label->getName() === $name) {
                return true;
            }
        }

        return false;
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

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): self
    {
        $this->direction = $direction;
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

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;
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

    public function addMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    // Convenience methods for checking status
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

    public function isQueued(): bool
    {
        return $this->status === self::STATUS_QUEUED;
    }

    /**
     * Check if notification failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if notification is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    // Convenience methods for source tracking via metadata
    public function getSource(): ?string
    {
        return $this->getMetadataValue('source');
    }

    public function setSource(string $source): self
    {
        return $this->addMetadata('source', $source);
    }

    public function isUserCreated(): bool
    {
        return $this->getSource() === 'user';
    }

    public function isSystemGenerated(): bool
    {
        return $this->getSource() === 'system';
    }

    public function isApiGenerated(): bool
    {
        return $this->getSource() === 'api';
    }
}