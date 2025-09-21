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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Repository\MessageRepository;
use Nkamuo\NotificationTrackerBundle\Controller\Api\RetryMessageController;
use Nkamuo\NotificationTrackerBundle\Controller\Api\CancelMessageController;
use Nkamuo\NotificationTrackerBundle\Config\ApiRoutes;
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
            normalizationContext: ['groups' => ['message:detail']]
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
    'notification.subject' => 'partial'
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'sentAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'sentAt', 'status'], arguments: ['orderParameterName' => 'order'])]
abstract class Message
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RETRYING = 'retrying';

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[Groups(['message:read', 'message:list', 'notification:read'])]
    private Ulid $id;

    #[ORM\Column(length: 50)]
    #[Groups(['message:read', 'message:list', 'message:write'])]
    #[Assert\Choice(choices: [
        self::STATUS_PENDING,
        self::STATUS_QUEUED,
        self::STATUS_SENDING,
        self::STATUS_SENT,
        self::STATUS_DELIVERED,
        self::STATUS_FAILED,
        self::STATUS_BOUNCED,
        self::STATUS_CANCELLED,
        self::STATUS_RETRYING
    ])]
    protected string $status = self::STATUS_PENDING;

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
    #[Groups(['message:detail'])]
    protected ?MessageContent $content = null;

    #[ORM\OneToMany(targetEntity: MessageRecipient::class, mappedBy: 'message', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['message:detail'])]
    protected Collection $recipients;

    #[ORM\OneToMany(targetEntity: MessageEvent::class, mappedBy: 'message', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['occurredAt' => 'DESC'])]
    #[Groups(['message:detail'])]
    protected Collection $events;

    #[ORM\OneToMany(targetEntity: MessageAttachment::class, mappedBy: 'message', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['message:detail'])]
    protected Collection $attachments;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->recipients = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->attachments = new ArrayCollection();
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
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
        $latestEvent = $this->events->first();
        if (!$latestEvent) {
            return null;
        }

        return [
            'type' => $latestEvent->getEventType(),
            'occurred_at' => $latestEvent->getOccurredAt(),
            'metadata' => $latestEvent->getMetadata(),
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

    abstract public function getType(): string;

    // Abstract method for getting subject (to be implemented by concrete classes)
    abstract public function getSubject(): ?string;
}