<?php

namespace Nkamuo\NotificationTrackerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;

#[ORM\Entity(repositoryClass: 'Nkamuo\NotificationTrackerBundle\Repository\ContactActivityRepository')]
#[ORM\Table(name: 'nt_contact_activity')]
#[ORM\Index(columns: ['contact_id', 'occurred_at'], name: 'idx_contact_activity_timeline')]
#[ORM\Index(columns: ['activity_type'], name: 'idx_contact_activity_type')]
#[ORM\Index(columns: ['occurred_at'], name: 'idx_contact_activity_occurred')]
#[ORM\Index(columns: ['source'], name: 'idx_contact_activity_source')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post()
    ],
    normalizationContext: ['groups' => ['contact_activity:read']],
    denormalizationContext: ['groups' => ['contact_activity:write']],
    routePrefix: '/notification-tracker',
)]
class ContactActivity
{
    public const TYPE_MESSAGE_SENT = 'message_sent';
    public const TYPE_MESSAGE_DELIVERED = 'message_delivered';
    public const TYPE_MESSAGE_OPENED = 'message_opened';
    public const TYPE_MESSAGE_CLICKED = 'message_clicked';
    public const TYPE_MESSAGE_BOUNCED = 'message_bounced';
    public const TYPE_MESSAGE_COMPLAINED = 'message_complained';
    public const TYPE_CHANNEL_ADDED = 'channel_added';
    public const TYPE_CHANNEL_VERIFIED = 'channel_verified';
    public const TYPE_CHANNEL_REMOVED = 'channel_removed';
    public const TYPE_OPTED_IN = 'opted_in';
    public const TYPE_OPTED_OUT = 'opted_out';
    public const TYPE_CONTACT_CREATED = 'contact_created';
    public const TYPE_CONTACT_UPDATED = 'contact_updated';
    public const TYPE_CONTACT_MERGED = 'contact_merged';
    public const TYPE_GROUP_ADDED = 'group_added';
    public const TYPE_GROUP_REMOVED = 'group_removed';
    public const TYPE_PREFERENCE_UPDATED = 'preference_updated';
    public const TYPE_VERIFICATION_REQUESTED = 'verification_requested';
    public const TYPE_VERIFICATION_COMPLETED = 'verification_completed';
    public const TYPE_VERIFICATION_FAILED = 'verification_failed';
    public const TYPE_ENGAGEMENT_SCORE_UPDATED = 'engagement_score_updated';
    public const TYPE_PROFILE_VIEWED = 'profile_viewed';
    public const TYPE_EXPORT_INCLUDED = 'export_included';
    public const TYPE_IMPORT_PROCESSED = 'import_processed';
    public const TYPE_API_ACCESS = 'api_access';
    public const TYPE_WEBHOOK_RECEIVED = 'webhook_received';
    public const TYPE_CUSTOM = 'custom';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    #[Groups(['contact_activity:read'])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Contact::class, inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private Contact $contact;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice([
        self::TYPE_MESSAGE_SENT,
        self::TYPE_MESSAGE_DELIVERED,
        self::TYPE_MESSAGE_OPENED,
        self::TYPE_MESSAGE_CLICKED,
        self::TYPE_MESSAGE_BOUNCED,
        self::TYPE_MESSAGE_COMPLAINED,
        self::TYPE_CHANNEL_ADDED,
        self::TYPE_CHANNEL_VERIFIED,
        self::TYPE_CHANNEL_REMOVED,
        self::TYPE_OPTED_IN,
        self::TYPE_OPTED_OUT,
        self::TYPE_CONTACT_CREATED,
        self::TYPE_CONTACT_UPDATED,
        self::TYPE_CONTACT_MERGED,
        self::TYPE_GROUP_ADDED,
        self::TYPE_GROUP_REMOVED,
        self::TYPE_PREFERENCE_UPDATED,
        self::TYPE_VERIFICATION_REQUESTED,
        self::TYPE_VERIFICATION_COMPLETED,
        self::TYPE_VERIFICATION_FAILED,
        self::TYPE_ENGAGEMENT_SCORE_UPDATED,
        self::TYPE_PROFILE_VIEWED,
        self::TYPE_EXPORT_INCLUDED,
        self::TYPE_IMPORT_PROCESSED,
        self::TYPE_API_ACCESS,
        self::TYPE_WEBHOOK_RECEIVED,
        self::TYPE_CUSTOM
    ])]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private string $activityType;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?string $source = null; // What triggered this activity

    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?string $relatedMessageId = null; // If related to a message

    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?string $relatedChannelId = null; // If related to a channel

    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?string $relatedGroupId = null; // If related to a group

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?array $metadata = null; // Additional activity data

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?array $oldValues = null; // Previous values (for update activities)

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?array $newValues = null; // New values (for update activities)

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?string $performedBy = null; // User who performed the action

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?string $performedByType = null; // user|system|api|webhook

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?string $userAgent = null; // For web-based activities

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?string $ipAddress = null; // For security tracking

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?string $location = null; // Geographic location if available

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?string $device = null; // Device type (mobile, desktop, etc.)

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private ?string $priority = null; // Activity priority level

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private bool $isSystemGenerated = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private bool $isVisible = true; // Whether to show in UI

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['contact_activity:read', 'contact_activity:write'])]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['contact_activity:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = $this->generateUlid();
        $this->occurredAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    private function generateUlid(): string
    {
        // Simple ULID-like generation - in production, use proper ULID library
        return sprintf(
            '%08x%04x%04x%04x%12x',
            time(),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffffffffffff)
        );
    }

    public static function createMessageActivity(
        Contact $contact,
        string $activityType,
        string $messageId,
        string $channelId = null,
        array $metadata = null
    ): self {
        $activity = new self();
        $activity->contact = $contact;
        $activity->activityType = $activityType;
        $activity->relatedMessageId = $messageId;
        $activity->relatedChannelId = $channelId;
        $activity->metadata = $metadata;
        $activity->isSystemGenerated = true;
        $activity->source = 'message_tracker';

        switch ($activityType) {
            case self::TYPE_MESSAGE_SENT:
                $activity->title = 'Message Sent';
                $activity->description = 'A message was sent to this contact';
                break;
            case self::TYPE_MESSAGE_DELIVERED:
                $activity->title = 'Message Delivered';
                $activity->description = 'A message was successfully delivered';
                break;
            case self::TYPE_MESSAGE_OPENED:
                $activity->title = 'Message Opened';
                $activity->description = 'Contact opened a message';
                break;
            case self::TYPE_MESSAGE_CLICKED:
                $activity->title = 'Message Clicked';
                $activity->description = 'Contact clicked a link in a message';
                break;
            case self::TYPE_MESSAGE_BOUNCED:
                $activity->title = 'Message Bounced';
                $activity->description = 'Message delivery failed (bounced)';
                break;
            case self::TYPE_MESSAGE_COMPLAINED:
                $activity->title = 'Spam Complaint';
                $activity->description = 'Contact marked message as spam';
                break;
        }

        return $activity;
    }

    public static function createChannelActivity(
        Contact $contact,
        string $activityType,
        string $channelId,
        array $metadata = null,
        string $performedBy = null
    ): self {
        $activity = new self();
        $activity->contact = $contact;
        $activity->activityType = $activityType;
        $activity->relatedChannelId = $channelId;
        $activity->metadata = $metadata;
        $activity->performedBy = $performedBy;
        $activity->isSystemGenerated = $performedBy === null;
        $activity->source = 'contact_manager';

        switch ($activityType) {
            case self::TYPE_CHANNEL_ADDED:
                $activity->title = 'Communication Channel Added';
                $activity->description = 'A new communication channel was added';
                break;
            case self::TYPE_CHANNEL_VERIFIED:
                $activity->title = 'Channel Verified';
                $activity->description = 'Communication channel was verified';
                break;
            case self::TYPE_CHANNEL_REMOVED:
                $activity->title = 'Channel Removed';
                $activity->description = 'Communication channel was removed';
                break;
            case self::TYPE_VERIFICATION_REQUESTED:
                $activity->title = 'Verification Requested';
                $activity->description = 'Channel verification was requested';
                break;
            case self::TYPE_VERIFICATION_COMPLETED:
                $activity->title = 'Verification Completed';
                $activity->description = 'Channel verification was completed successfully';
                break;
            case self::TYPE_VERIFICATION_FAILED:
                $activity->title = 'Verification Failed';
                $activity->description = 'Channel verification failed';
                break;
        }

        return $activity;
    }

    public static function createContactActivity(
        Contact $contact,
        string $activityType,
        array $oldValues = null,
        array $newValues = null,
        string $performedBy = null
    ): self {
        $activity = new self();
        $activity->contact = $contact;
        $activity->activityType = $activityType;
        $activity->oldValues = $oldValues;
        $activity->newValues = $newValues;
        $activity->performedBy = $performedBy;
        $activity->isSystemGenerated = $performedBy === null;
        $activity->source = 'contact_manager';

        switch ($activityType) {
            case self::TYPE_CONTACT_CREATED:
                $activity->title = 'Contact Created';
                $activity->description = 'Contact profile was created';
                break;
            case self::TYPE_CONTACT_UPDATED:
                $activity->title = 'Contact Updated';
                $activity->description = 'Contact profile was updated';
                break;
            case self::TYPE_CONTACT_MERGED:
                $activity->title = 'Contact Merged';
                $activity->description = 'Contact was merged with another contact';
                break;
            case self::TYPE_OPTED_IN:
                $activity->title = 'Opted In';
                $activity->description = 'Contact opted in to receive communications';
                break;
            case self::TYPE_OPTED_OUT:
                $activity->title = 'Opted Out';
                $activity->description = 'Contact opted out of communications';
                break;
        }

        return $activity;
    }

    public static function createGroupActivity(
        Contact $contact,
        string $activityType,
        string $groupId,
        string $performedBy = null
    ): self {
        $activity = new self();
        $activity->contact = $contact;
        $activity->activityType = $activityType;
        $activity->relatedGroupId = $groupId;
        $activity->performedBy = $performedBy;
        $activity->isSystemGenerated = $performedBy === null;
        $activity->source = 'group_manager';

        switch ($activityType) {
            case self::TYPE_GROUP_ADDED:
                $activity->title = 'Added to Group';
                $activity->description = 'Contact was added to a group';
                break;
            case self::TYPE_GROUP_REMOVED:
                $activity->title = 'Removed from Group';
                $activity->description = 'Contact was removed from a group';
                break;
        }

        return $activity;
    }

    public function setMetadata(string $key, mixed $value): self
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        return $this;
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return ($this->metadata ?? [])[$key] ?? $default;
    }

    public function isEngagementActivity(): bool
    {
        return in_array($this->activityType, [
            self::TYPE_MESSAGE_OPENED,
            self::TYPE_MESSAGE_CLICKED,
        ]);
    }

    public function isNegativeActivity(): bool
    {
        return in_array($this->activityType, [
            self::TYPE_MESSAGE_BOUNCED,
            self::TYPE_MESSAGE_COMPLAINED,
            self::TYPE_OPTED_OUT,
            self::TYPE_VERIFICATION_FAILED,
        ]);
    }

    public function isPositiveActivity(): bool
    {
        return in_array($this->activityType, [
            self::TYPE_MESSAGE_DELIVERED,
            self::TYPE_MESSAGE_OPENED,
            self::TYPE_MESSAGE_CLICKED,
            self::TYPE_CHANNEL_VERIFIED,
            self::TYPE_OPTED_IN,
            self::TYPE_VERIFICATION_COMPLETED,
        ]);
    }

    public function getActivityIcon(): string
    {
        return match ($this->activityType) {
            self::TYPE_MESSAGE_SENT => '📤',
            self::TYPE_MESSAGE_DELIVERED => '✅',
            self::TYPE_MESSAGE_OPENED => '👁️',
            self::TYPE_MESSAGE_CLICKED => '🖱️',
            self::TYPE_MESSAGE_BOUNCED => '❌',
            self::TYPE_MESSAGE_COMPLAINED => '🚫',
            self::TYPE_CHANNEL_ADDED => '➕',
            self::TYPE_CHANNEL_VERIFIED => '✓',
            self::TYPE_CHANNEL_REMOVED => '➖',
            self::TYPE_OPTED_IN => '✅',
            self::TYPE_OPTED_OUT => '🚫',
            self::TYPE_CONTACT_CREATED => '🆕',
            self::TYPE_CONTACT_UPDATED => '✏️',
            self::TYPE_CONTACT_MERGED => '🔗',
            self::TYPE_GROUP_ADDED => '👥',
            self::TYPE_GROUP_REMOVED => '👤',
            self::TYPE_PREFERENCE_UPDATED => '⚙️',
            self::TYPE_VERIFICATION_REQUESTED => '📧',
            self::TYPE_VERIFICATION_COMPLETED => '✅',
            self::TYPE_VERIFICATION_FAILED => '❌',
            self::TYPE_ENGAGEMENT_SCORE_UPDATED => '📊',
            self::TYPE_PROFILE_VIEWED => '👀',
            self::TYPE_EXPORT_INCLUDED => '📤',
            self::TYPE_IMPORT_PROCESSED => '📥',
            self::TYPE_API_ACCESS => '🔌',
            self::TYPE_WEBHOOK_RECEIVED => '🔗',
            default => '📝',
        };
    }

    // Getters and Setters
    public function getId(): string
    {
        return $this->id;
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function setContact(Contact $contact): self
    {
        $this->contact = $contact;
        return $this;
    }

    public function getActivityType(): string
    {
        return $this->activityType;
    }

    public function setActivityType(string $activityType): self
    {
        $this->activityType = $activityType;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
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

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getRelatedMessageId(): ?string
    {
        return $this->relatedMessageId;
    }

    public function setRelatedMessageId(?string $relatedMessageId): self
    {
        $this->relatedMessageId = $relatedMessageId;
        return $this;
    }

    public function getRelatedChannelId(): ?string
    {
        return $this->relatedChannelId;
    }

    public function setRelatedChannelId(?string $relatedChannelId): self
    {
        $this->relatedChannelId = $relatedChannelId;
        return $this;
    }

    public function getRelatedGroupId(): ?string
    {
        return $this->relatedGroupId;
    }

    public function setRelatedGroupId(?string $relatedGroupId): self
    {
        $this->relatedGroupId = $relatedGroupId;
        return $this;
    }

    public function getMetadataArray(): ?array
    {
        return $this->metadata;
    }

    public function setMetadataArray(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getOldValues(): ?array
    {
        return $this->oldValues;
    }

    public function setOldValues(?array $oldValues): self
    {
        $this->oldValues = $oldValues;
        return $this;
    }

    public function getNewValues(): ?array
    {
        return $this->newValues;
    }

    public function setNewValues(?array $newValues): self
    {
        $this->newValues = $newValues;
        return $this;
    }

    public function getPerformedBy(): ?string
    {
        return $this->performedBy;
    }

    public function setPerformedBy(?string $performedBy): self
    {
        $this->performedBy = $performedBy;
        return $this;
    }

    public function getPerformedByType(): ?string
    {
        return $this->performedByType;
    }

    public function setPerformedByType(?string $performedByType): self
    {
        $this->performedByType = $performedByType;
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

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getDevice(): ?string
    {
        return $this->device;
    }

    public function setDevice(?string $device): self
    {
        $this->device = $device;
        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(?string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getIsSystemGenerated(): bool
    {
        return $this->isSystemGenerated;
    }

    public function setIsSystemGenerated(bool $isSystemGenerated): self
    {
        $this->isSystemGenerated = $isSystemGenerated;
        return $this;
    }

    public function getIsVisible(): bool
    {
        return $this->isVisible;
    }

    public function setIsVisible(bool $isVisible): self
    {
        $this->isVisible = $isVisible;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
