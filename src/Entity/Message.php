<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Nkamuo\NotificationTrackerBundle\ApiPlatform\Filter\NotInFilter;
use Nkamuo\NotificationTrackerBundle\ApiPlatform\Filter\NotEqualsFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Repository\MessageRepository;
use Nkamuo\NotificationTrackerBundle\Controller\Api\RetryMessageController;
use Nkamuo\NotificationTrackerBundle\Controller\Api\CancelMessageController;
use Nkamuo\NotificationTrackerBundle\Config\ApiRoutes;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationDirection;
use Nkamuo\NotificationTrackerBundle\Enum\MessageStatus;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'notification_tracker_messages')]
#[ORM\Index(name: 'idx_nt_message_status', columns: ['status'])]
#[ORM\Index(name: 'idx_nt_message_sent_at', columns: ['sent_at'])]
#[ORM\Index(name: 'idx_nt_message_notification', columns: ['notification_id'])]
#[ORM\Index(name: 'idx_nt_message_stamp_id', columns: ['messenger_stamp_id'])]
#[ORM\Index(name: 'idx_nt_message_fingerprint', columns: ['content_fingerprint'])]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap([
    'email' => EmailMessage::class,
    'sms' => SmsMessage::class,
    'slack' => SlackMessage::class,
    'telegram' => TelegramMessage::class,
    'push' => PushMessage::class,
])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Message',
    description: 'Tracked notification message',
    normalizationContext: ['groups' => ['message:read']],
    denormalizationContext: ['groups' => ['message:write']],
    operations: [
        new GetCollection(
            uriTemplate: ApiRoutes::MESSAGES,
            normalizationContext: ['groups' => ['message:list']],
            paginationItemsPerPage: 25,
            paginationMaximumItemsPerPage: 100,
            paginationPartial: true
        ),
        new Get(
            uriTemplate: ApiRoutes::MESSAGES . '/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            normalizationContext: ['groups' => ['message:list','message:detail', 'message:read']]
        ),
        new Post(
            uriTemplate: ApiRoutes::MESSAGES . '/{id}/retry',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            controller: RetryMessageController::class,
            name: 'notification_tracker_retry_message'
        ),
        new Post(
            uriTemplate: ApiRoutes::MESSAGES . '/{id}/cancel',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            controller: CancelMessageController::class,
            name: 'notification_tracker_cancel_message'
        ),
        new Delete(
            uriTemplate: ApiRoutes::MESSAGES . '/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}']
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'status' => 'exact',
    'type' => 'exact',
    'transportName' => 'partial',
    'subject' => 'partial',
    'notification.type' => 'exact',
    'notification.subject' => 'partial',
    'notification.direction' => 'exact',
    'labels.name' => 'exact'
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'sentAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'sentAt', 'status'], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(NotInFilter::class, properties: [
    'status' => null,
    'type' => null,
    'transportName' => null,
    'notification.status' => null,
    'notification.type' => null,
    'notification.direction' => null
])]
#[ApiFilter(NotEqualsFilter::class, properties: [
    'status' => null,
    'type' => null,
    'transportName' => null,
    'subject' => null,
    'notification.status' => null,
    'notification.type' => null,
    'notification.direction' => null,
    'notification.subject' => null
])]
abstract class Message
{
    // Status constants - DEPRECATED: Use MessageStatus enum instead
    /** @deprecated Use MessageStatus::PENDING instead */
    public const STATUS_PENDING = 'pending';
    /** @deprecated Use MessageStatus::QUEUED instead */
    public const STATUS_QUEUED = 'queued';
    /** @deprecated Use MessageStatus::SENDING instead */
    public const STATUS_SENDING = 'sending';
    /** @deprecated Use MessageStatus::SENT instead */
    public const STATUS_SENT = 'sent';
    /** @deprecated Use MessageStatus::DELIVERED instead */
    public const STATUS_DELIVERED = 'delivered';
    /** @deprecated Use MessageStatus::FAILED instead */
    public const STATUS_FAILED = 'failed';
    /** @deprecated Use MessageStatus::BOUNCED instead */
    public const STATUS_BOUNCED = 'bounced';
    /** @deprecated Use MessageStatus::CANCELLED instead */
    public const STATUS_CANCELLED = 'cancelled';
    /** @deprecated Use MessageStatus::RETRYING instead */
    public const STATUS_RETRYING = 'retrying';

    // Direction constants - DEPRECATED: Use NotificationDirection enum instead
    /** @deprecated Use NotificationDirection::OUTBOUND instead */
    public const DIRECTION_OUTBOUND = 'outbound';
    /** @deprecated Use NotificationDirection::INBOUND instead */
    public const DIRECTION_INBOUND = 'inbound';
    /** @deprecated Use NotificationDirection::DRAFT instead */
    public const DIRECTION_DRAFT = 'draft';

    /** @deprecated Use NotificationDirection::values() instead */
    public const ALLOWED_DIRECTIONS = [
        self::DIRECTION_OUTBOUND,
        self::DIRECTION_INBOUND,
        self::DIRECTION_DRAFT,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[Groups(['message:read', 'message:list', 'notification:read'])]
    private Ulid $id;

    #[ORM\Column(type: 'string', enumType: MessageStatus::class)]
    #[Groups(['message:read', 'message:list', 'message:write'])]
    protected MessageStatus $status = MessageStatus::PENDING;

    #[ORM\Column(type: 'string', enumType: NotificationDirection::class)]
    #[Groups(['message:read', 'message:list', 'message:write'])]
    protected NotificationDirection $direction = NotificationDirection::OUTBOUND;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['message:read', 'message:write', 'message:list'])]
    protected ?string $transportName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['message:read', 'message:detail'])]
    protected ?string $transportDsn = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['message:read', 'message:write', 'message:detail'])]
    protected array $metadata = [];

    #[ORM\Column(length: 36, nullable: true)]
    #[Groups(['message:read', 'message:detail'])]
    protected ?string $messengerStampId = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['message:read', 'message:detail'])]
    protected ?string $contentFingerprint = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['message:read', 'message:list', 'message:detail'])]
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['message:read', 'message:detail'])]
    protected ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['message:read', 'message:write', 'message:list'])]
    protected ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['message:read', 'message:list', 'message:detail'])]
    protected ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['message:read', 'message:write', 'message:list'])]
    protected bool $hasScheduleOverride = false;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['message:read', 'message:list'])]
    protected int $retryCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['message:read', 'message:list'])]
    protected ?string $failureReason = null;

    #[ORM\ManyToOne(targetEntity: Notification::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['message:read', 'message:list'])]
    protected ?Notification $notification = null;

    #[ORM\ManyToOne(targetEntity: MessageTemplate::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['message:read', 'message:detail'])]
    protected ?MessageTemplate $template = null;

    #[ORM\OneToOne(targetEntity: MessageContent::class, mappedBy: 'message', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['message:detail', 'message:read'])]
    protected ?MessageContent $content = null;

    #[ORM\OneToMany(targetEntity: MessageRecipient::class, mappedBy: 'message', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['message:detail', 'message:read'])]
    protected Collection $recipients;

    #[ORM\OneToMany(targetEntity: MessageEvent::class, mappedBy: 'message', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['occurredAt' => 'DESC'])]
    #[Groups(['message:detail'])]
    protected Collection $events;

    #[ORM\OneToMany(targetEntity: MessageAttachment::class, mappedBy: 'message', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['message:detail'])]
    protected Collection $attachments;

    #[ORM\ManyToMany(targetEntity: Label::class, inversedBy: 'messages')]
    #[ORM\JoinTable(name: 'nt_message_labels')]
    #[Groups(['message:read', 'message:write', 'message:list'])]
    protected Collection $labels;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->recipients = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->labels = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getStatus(): MessageStatus
    {
        return $this->status;
    }

    public function setStatus(MessageStatus|string $status): self
    {
        $this->status = $status instanceof MessageStatus ? $status : MessageStatus::from($status);
        return $this;
    }

    public function getDirection(): NotificationDirection
    {
        return $this->direction;
    }

    public function setDirection(NotificationDirection|string $direction): self
    {
        $this->direction = $direction instanceof NotificationDirection ? $direction : NotificationDirection::from($direction);
        return $this;
    }

    public function isInbound(): bool
    {
        return $this->direction === NotificationDirection::INBOUND;
    }

    public function isOutbound(): bool
    {
        return $this->direction === NotificationDirection::OUTBOUND;
    }

    public function getTransportName(): ?string
    {
        return $this->transportName;
    }

    public function setTransportName(?string $transportName): self
    {
        $this->transportName = $transportName;
        return $this;
    }

    public function getTransportDsn(): ?string
    {
        return $this->transportDsn;
    }

    public function setTransportDsn(?string $transportDsn): self
    {
        $this->transportDsn = $transportDsn;
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

    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function getMessengerStampId(): ?string
    {
        return $this->messengerStampId;
    }

    public function setMessengerStampId(?string $messengerStampId): self
    {
        $this->messengerStampId = $messengerStampId;
        return $this;
    }

    public function getContentFingerprint(): ?string
    {
        return $this->contentFingerprint;
    }

    public function setContentFingerprint(?string $contentFingerprint): self
    {
        $this->contentFingerprint = $contentFingerprint;
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

    public function getHasScheduleOverride(): bool
    {
        return $this->hasScheduleOverride;
    }

    public function setHasScheduleOverride(bool $hasScheduleOverride): self
    {
        $this->hasScheduleOverride = $hasScheduleOverride;
        return $this;
    }

    /**
     * Set scheduled time with override flag
     */
    public function scheduleFor(\DateTimeImmutable $scheduledAt, bool $isOverride = false): self
    {
        $this->scheduledAt = $scheduledAt;
        $this->hasScheduleOverride = $isOverride;
        return $this;
    }

    /**
     * Get the effective scheduled time (message override or notification default)
     */
    public function getEffectiveScheduledAt(): ?\DateTimeImmutable
    {
        // If message has its own schedule override, use it
        if ($this->hasScheduleOverride && $this->scheduledAt) {
            return $this->scheduledAt;
        }

        // Otherwise, use notification's scheduled time
        if ($this->notification && $this->notification->getScheduledAt()) {
            return $this->notification->getScheduledAt();
        }

        // Fall back to message's own scheduledAt
        return $this->scheduledAt;
    }

    /**
     * Check if message is ready to send based on effective schedule
     */
    public function isReadyToSend(\DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable();
        $effectiveTime = $this->getEffectiveScheduledAt();

        if (!$effectiveTime) {
            return true; // No schedule = ready to send
        }

        return $now >= $effectiveTime;
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

    public function incrementRetryCount(): self
    {
        $this->retryCount++;
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

    public function getNotification(): ?Notification
    {
        return $this->notification;
    }

    public function setNotification(?Notification $notification): self
    {
        $this->notification = $notification;
        return $this;
    }

    public function getTemplate(): ?MessageTemplate
    {
        return $this->template;
    }

    public function setTemplate(?MessageTemplate $template): self
    {
        $this->template = $template;
        return $this;
    }

    public function getContent(): ?MessageContent
    {
        return $this->content;
    }

    public function setContent(?MessageContent $content): self
    {
        $this->content = $content;
        if ($content !== null) {
            $content->setMessage($this);
        }
        return $this;
    }

    public function getRecipients(): Collection
    {
        return $this->recipients;
    }

    public function addRecipient(MessageRecipient $recipient): self
    {
        if (!$this->recipients->contains($recipient)) {
            $this->recipients->add($recipient);
            $recipient->setMessage($this);
        }
        return $this;
    }

    public function removeRecipient(MessageRecipient $recipient): self
    {
        $this->recipients->removeElement($recipient);
        return $this;
    }

    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(MessageEvent $event): self
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setMessage($this);
        }
        return $this;
    }

    public function removeEvent(MessageEvent $event): self
    {
        $this->events->removeElement($event);
        return $this;
    }

    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(MessageAttachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
            $attachment->setMessage($this);
        }
        return $this;
    }

    public function removeAttachment(MessageAttachment $attachment): self
    {
        $this->attachments->removeElement($attachment);
        return $this;
    }

    /**
     * Get message type (used for API serialization)
     */
    #[Groups(['message:list', 'message:detail'])]
    public function getMessageType(): string
    {
        return $this->getType();
    }

    /**
     * Get total recipient count
     */
    #[Groups(['message:list', 'message:detail'])]
    public function getRecipientCount(): int
    {
        return $this->recipients->count();
    }

    /**
     * Get engagement statistics
     */
    #[Groups(['message:list', 'message:detail'])]
    public function getEngagementStats(): array
    {
        $stats = [
            'total_recipients' => $this->recipients->count(),
            'opened' => 0,
            'clicked' => 0,
            'bounced' => 0,
            'total_opens' => 0,
            'total_clicks' => 0,
        ];

        foreach ($this->recipients as $recipient) {
            if ($recipient->getOpenedAt()) {
                $stats['opened']++;
            }
            if ($recipient->getClickedAt()) {
                $stats['clicked']++;
            }
            if ($recipient->getBouncedAt()) {
                $stats['bounced']++;
            }
            $stats['total_opens'] += $recipient->getOpenCount();
            $stats['total_clicks'] += $recipient->getClickCount();
        }

        return $stats;
    }

    /**
     * Get primary recipient address
     */
    #[Groups(['message:list', 'message:detail'])]
    public function getPrimaryRecipient(): ?string
    {
        foreach ($this->recipients as $recipient) {
            if ($recipient->getType() === MessageRecipient::TYPE_TO) {
                return $recipient->getAddress();
            }
        }
        return $this->recipients->first() ? $this->recipients->first()->getAddress() : null;
    }

    /**
     * Get short subject for list views
     */
    #[Groups(['message:list'])]
    public function getShortSubject(): ?string
    {
        $subject = $this->getSubject();
        if ($subject && strlen($subject) > 80) {
            return substr($subject, 0, 77) . '...';
        }
        return $subject;
    }

    /**
     * Get the latest event information
     */
    #[Groups(['message:list', 'message:detail'])]
    public function getLatestEvent(): ?array
    {
        // Get events ordered by occurredAt DESC (newest first)
        $events = $this->events->toArray();
        if (empty($events)) {
            return null;
        }

        // Sort by occurredAt DESC, then by ID DESC for deterministic ordering
        usort($events, function($a, $b) {
            $timeComparison = $b->getOccurredAt() <=> $a->getOccurredAt();
            if ($timeComparison === 0) {
                // If times are equal, use ID as tiebreaker (newer ID = later event)
                return $b->getId() <=> $a->getId();
            }
            return $timeComparison;
        });

        $latestEvent = $events[0];

        return [
            'type' => $latestEvent->getEventType(),
            'occurred_at' => $latestEvent->getOccurredAt(),
            'metadata' => $latestEvent->getEventData(),
        ];
    }

    /**
     * Get notification summary for message list
     */
    #[Groups(['message:list'])]
    public function getNotificationSummary(): ?array
    {
        if (!$this->notification) {
            return null;
        }

        return [
            'id' => (string) $this->notification->getId(),
            'type' => $this->notification->getType(),
            'subject' => $this->notification->getSubject(),
            'importance' => $this->notification->getImportance(),
        ];
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
            $label->addMessage($this);
        }

        return $this;
    }

    public function removeLabel(Label $label): self
    {
        if ($this->labels->removeElement($label)) {
            $label->removeMessage($this);
        }

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

    // ========================================
    // ENUM HELPER METHODS
    // ========================================

    /**
     * Get status as enum (alias for getStatus() for backward compatibility)
     * 
     * @return MessageStatus
     */
    public function getStatusEnum(): MessageStatus
    {
        return $this->status;
    }

    /**
     * Set status from enum (alias for setStatus() for backward compatibility)
     * 
     * @param MessageStatus $status
     * @return self
     */
    public function setStatusEnum(MessageStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Get direction as enum (alias for getDirection() for backward compatibility)
     * 
     * @return NotificationDirection
     */
    public function getDirectionEnum(): NotificationDirection
    {
        return $this->direction;
    }

    /**
     * Set direction from enum (alias for setDirection() for backward compatibility)
     * 
     * @param NotificationDirection $direction
     * @return self
     */
    public function setDirectionEnum(NotificationDirection $direction): self
    {
        $this->direction = $direction;
        return $this;
    }

    /**
     * Check if message is in draft state
     * 
     * @return bool
     */
    public function isDirectionDraft(): bool
    {
        return $this->direction->isDraft();
    }

    /**
     * Check if status indicates the message is active/processable
     * 
     * @return bool
     */
    public function isStatusActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Check if status indicates the message is completed
     * 
     * @return bool
     */
    public function isStatusCompleted(): bool
    {
        return $this->status->isCompleted();
    }

    /**
     * Check if status indicates successful delivery
     * 
     * @return bool
     */
    public function isStatusSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    /**
     * Check if status indicates failure
     * 
     * @return bool
     */
    public function isStatusFailed(): bool
    {
        return $this->status->isFailed();
    }

    /**
     * Check if status can be retried
     * 
     * @return bool
     */
    public function canBeRetried(): bool
    {
        return $this->status->canBeRetried();
    }

    /**
     * Transition to the next logical status
     * 
     * @return bool True if transition was successful, false if no valid transition exists
     */
    public function transitionToNextStatus(): bool
    {
        $nextStatus = $this->status->getNextStatus();
        
        if ($nextStatus !== null) {
            $this->status = $nextStatus;
            return true;
        }
        
        return false;
    }

    /**
     * Check if transition to given status is valid
     * 
     * @param MessageStatus $newStatus
     * @return bool
     */
    public function canTransitionTo(MessageStatus $newStatus): bool
    {
        return in_array($newStatus, $this->status->getValidTransitions());
    }

    /**
     * Safely transition to new status if valid
     * 
     * @param MessageStatus $newStatus
     * @return bool True if transition was successful, false if invalid
     */
    public function safeTransitionTo(MessageStatus $newStatus): bool
    {
        if ($this->canTransitionTo($newStatus)) {
            $this->status = $newStatus;
            return true;
        }
        
        return false;
    }

    /**
     * Automatically set direction based on context
     * 
     * @param bool $isOutbound Whether this is an outbound message
     * @return self
     */
    public function autoSetDirection(bool $isOutbound = true): self
    {
        $this->direction = $isOutbound ? NotificationDirection::OUTBOUND : NotificationDirection::INBOUND;
        return $this;
    }

    // ========================================
    // REFERENCE METHODS
    // ========================================

    /**
     * Set a reference value
     * 
     * @param string $key
     * @param string $value
     * @return self
     */
    public function setRef(string $key, string $value): self
    {
        if ($this->metadata === null) {
            $this->metadata = [];
        }
        
        if (!isset($this->metadata['refs'])) {
            $this->metadata['refs'] = [];
        }
        
        $this->metadata['refs'][$key] = $value;
        return $this;
    }

    /**
     * Get a reference value
     * 
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public function getRef(string $key, ?string $default = null): ?string
    {
        return $this->metadata['refs'][$key] ?? $default;
    }

    /**
     * Check if a reference exists
     * 
     * @param string $key
     * @return bool
     */
    public function hasRef(string $key): bool
    {
        return isset($this->metadata['refs'][$key]);
    }

    /**
     * Remove a reference
     * 
     * @param string $key
     * @return self
     */
    public function removeRef(string $key): self
    {
        if (isset($this->metadata['refs'][$key])) {
            unset($this->metadata['refs'][$key]);
        }
        return $this;
    }

    /**
     * Get all references
     * 
     * @return array
     */
    public function getRefs(): array
    {
        return $this->metadata['refs'] ?? [];
    }

    /**
     * Set multiple references at once
     * 
     * @param array $refs
     * @return self
     */
    public function setRefs(array $refs): self
    {
        if ($this->metadata === null) {
            $this->metadata = [];
        }
        
        $this->metadata['refs'] = $refs;
        return $this;
    }

    /**
     * Clear all references
     * 
     * @return self
     */
    public function clearRefs(): self
    {
        if (isset($this->metadata['refs'])) {
            unset($this->metadata['refs']);
        }
        return $this;
    }

    abstract public function getType(): string;

    // Abstract method for getting subject (to be implemented by concrete classes)
    abstract public function getSubject(): ?string;
}