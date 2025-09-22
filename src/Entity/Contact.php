<?php

namespace Nkamuo\NotificationTrackerBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;

#[ORM\Entity(repositoryClass: 'Nkamuo\NotificationTrackerBundle\Repository\ContactRepository')]
#[ORM\Table(name: 'nt_contact')]
#[ORM\Index(columns: ['status'], name: 'idx_contact_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_contact_created')]
#[ORM\Index(columns: ['last_contacted_at'], name: 'idx_contact_last_contacted')]
#[ORM\Index(columns: ['email_hash'], name: 'idx_contact_email_hash')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['contact:read']],
    denormalizationContext: ['groups' => ['contact:write']]
)]
class Contact
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_MERGED = 'merged';

    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_ORGANIZATION = 'organization';
    public const TYPE_DEPARTMENT = 'department';
    public const TYPE_ROLE = 'role';
    public const TYPE_SYSTEM = 'system';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    #[Groups(['contact:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice([
        self::TYPE_INDIVIDUAL,
        self::TYPE_ORGANIZATION,
        self::TYPE_DEPARTMENT,
        self::TYPE_ROLE,
        self::TYPE_SYSTEM
    ])]
    #[Groups(['contact:read', 'contact:write'])]
    private string $type = self::TYPE_INDIVIDUAL;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice([
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_BLOCKED,
        self::STATUS_PENDING_VERIFICATION,
        self::STATUS_MERGED
    ])]
    #[Groups(['contact:read', 'contact:write'])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $firstName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $lastName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $displayName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $organizationName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $jobTitle = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $department = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $language = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $timezone = null;

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    #[Assert\Length(max: 3)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $currency = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    #[Groups(['contact:read'])]
    private ?string $emailHash = null; // For deduplication without storing actual email

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?array $tags = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?array $customFields = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?array $preferences = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $source = null; // Where this contact came from

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $externalId = null; // ID in external system

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['contact:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['contact:read'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['contact:read'])]
    private ?\DateTimeImmutable $lastContactedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['contact:read'])]
    private ?\DateTimeImmutable $lastEngagedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['contact:read'])]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['contact:read'])]
    private int $engagementScore = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['contact:read'])]
    private int $totalMessagesSent = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['contact:read'])]
    private int $totalMessagesDelivered = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['contact:read'])]
    private int $totalMessagesOpened = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['contact:read'])]
    private int $totalMessagesClicked = 0;

    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    #[Groups(['contact:read'])]
    private ?string $mergedIntoContactId = null; // If this contact was merged into another

    // Relationships
    #[ORM\OneToMany(mappedBy: 'contact', targetEntity: ContactChannel::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['contact:read'])]
    private Collection $channels;

    #[ORM\OneToMany(mappedBy: 'contact', targetEntity: MessageRecipient::class)]
    #[Groups(['contact:read'])]
    private Collection $messageRecipients;

    #[ORM\ManyToMany(targetEntity: ContactGroup::class, inversedBy: 'contacts')]
    #[ORM\JoinTable(name: 'nt_contact_group_membership')]
    #[Groups(['contact:read', 'contact:write'])]
    private Collection $groups;

    #[ORM\OneToMany(mappedBy: 'contact', targetEntity: ContactActivity::class)]
    #[Groups(['contact:read'])]
    private Collection $activities;

    public function __construct()
    {
        $this->id = $this->generateUlid();
        $this->channels = new ArrayCollection();
        $this->messageRecipients = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->activities = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getFullName(): string
    {
        if ($this->displayName) {
            return $this->displayName;
        }

        if ($this->type === self::TYPE_ORGANIZATION) {
            return $this->organizationName ?? 'Unknown Organization';
        }

        $parts = array_filter([$this->firstName, $this->lastName]);
        return implode(' ', $parts) ?: 'Unknown Contact';
    }

    public function getPrimaryChannel(string $type): ?ContactChannel
    {
        foreach ($this->channels as $channel) {
            if ($channel->getType() === $type && $channel->isPrimary()) {
                return $channel;
            }
        }
        return null;
    }

    public function getActiveChannels(string $type): Collection
    {
        return $this->channels->filter(function (ContactChannel $channel) use ($type) {
            return $channel->getType() === $type && $channel->isActive();
        });
    }

    public function addTag(string $tag): self
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->tags = $tags;
        }
        return $this;
    }

    public function removeTag(string $tag): self
    {
        $tags = $this->tags ?? [];
        $this->tags = array_values(array_filter($tags, fn($t) => $t !== $tag));
        return $this;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? []);
    }

    public function setCustomField(string $key, mixed $value): self
    {
        $fields = $this->customFields ?? [];
        $fields[$key] = $value;
        $this->customFields = $fields;
        return $this;
    }

    public function getCustomField(string $key, mixed $default = null): mixed
    {
        return ($this->customFields ?? [])[$key] ?? $default;
    }

    public function updateEngagementScore(): void
    {
        // Calculate engagement score based on activity
        $score = 0;
        $score += $this->totalMessagesOpened * 2;
        $score += $this->totalMessagesClicked * 5;
        
        // Recency bonus
        if ($this->lastEngagedAt) {
            $daysSinceEngagement = (new \DateTime())->diff($this->lastEngagedAt->format('Y-m-d'))->days;
            if ($daysSinceEngagement < 7) {
                $score += 10;
            } elseif ($daysSinceEngagement < 30) {
                $score += 5;
            }
        }

        $this->engagementScore = min($score, 100); // Cap at 100
    }

    // Getters and Setters
    public function getId(): string
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName;
        return $this;
    }

    public function getOrganizationName(): ?string
    {
        return $this->organizationName;
    }

    public function setOrganizationName(?string $organizationName): self
    {
        $this->organizationName = $organizationName;
        return $this;
    }

    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }

    public function setJobTitle(?string $jobTitle): self
    {
        $this->jobTitle = $jobTitle;
        return $this;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): self
    {
        $this->department = $department;
        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getEmailHash(): ?string
    {
        return $this->emailHash;
    }

    public function setEmailHash(?string $emailHash): self
    {
        $this->emailHash = $emailHash;
        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): self
    {
        $this->customFields = $customFields;
        return $this;
    }

    public function getPreferences(): ?array
    {
        return $this->preferences;
    }

    public function setPreferences(?array $preferences): self
    {
        $this->preferences = $preferences;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
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

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastContactedAt(): ?\DateTimeImmutable
    {
        return $this->lastContactedAt;
    }

    public function setLastContactedAt(?\DateTimeImmutable $lastContactedAt): self
    {
        $this->lastContactedAt = $lastContactedAt;
        return $this;
    }

    public function getLastEngagedAt(): ?\DateTimeImmutable
    {
        return $this->lastEngagedAt;
    }

    public function setLastEngagedAt(?\DateTimeImmutable $lastEngagedAt): self
    {
        $this->lastEngagedAt = $lastEngagedAt;
        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): self
    {
        $this->verifiedAt = $verifiedAt;
        return $this;
    }

    public function getEngagementScore(): int
    {
        return $this->engagementScore;
    }

    public function setEngagementScore(int $engagementScore): self
    {
        $this->engagementScore = $engagementScore;
        return $this;
    }

    public function getTotalMessagesSent(): int
    {
        return $this->totalMessagesSent;
    }

    public function setTotalMessagesSent(int $totalMessagesSent): self
    {
        $this->totalMessagesSent = $totalMessagesSent;
        return $this;
    }

    public function getTotalMessagesDelivered(): int
    {
        return $this->totalMessagesDelivered;
    }

    public function setTotalMessagesDelivered(int $totalMessagesDelivered): self
    {
        $this->totalMessagesDelivered = $totalMessagesDelivered;
        return $this;
    }

    public function getTotalMessagesOpened(): int
    {
        return $this->totalMessagesOpened;
    }

    public function setTotalMessagesOpened(int $totalMessagesOpened): self
    {
        $this->totalMessagesOpened = $totalMessagesOpened;
        return $this;
    }

    public function getTotalMessagesClicked(): int
    {
        return $this->totalMessagesClicked;
    }

    public function setTotalMessagesClicked(int $totalMessagesClicked): self
    {
        $this->totalMessagesClicked = $totalMessagesClicked;
        return $this;
    }

    public function getMergedIntoContactId(): ?string
    {
        return $this->mergedIntoContactId;
    }

    public function setMergedIntoContactId(?string $mergedIntoContactId): self
    {
        $this->mergedIntoContactId = $mergedIntoContactId;
        return $this;
    }

    public function getChannels(): Collection
    {
        return $this->channels;
    }

    public function addChannel(ContactChannel $channel): self
    {
        if (!$this->channels->contains($channel)) {
            $this->channels[] = $channel;
            $channel->setContact($this);
        }
        return $this;
    }

    public function removeChannel(ContactChannel $channel): self
    {
        if ($this->channels->removeElement($channel)) {
            if ($channel->getContact() === $this) {
                $channel->setContact(null);
            }
        }
        return $this;
    }

    public function getMessageRecipients(): Collection
    {
        return $this->messageRecipients;
    }

    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function addGroup(ContactGroup $group): self
    {
        if (!$this->groups->contains($group)) {
            $this->groups[] = $group;
        }
        return $this;
    }

    public function removeGroup(ContactGroup $group): self
    {
        $this->groups->removeElement($group);
        return $this;
    }

    public function getActivities(): Collection
    {
        return $this->activities;
    }

    /**
     * Get the last event across all messages sent to this contact
     */
    #[Groups(['contact:read'])]
    public function getLastEvent(): ?array
    {
        $allEvents = [];
        
        // Collect all events from all messages sent to this contact
        foreach ($this->messageRecipients as $recipient) {
            $message = $recipient->getMessage();
            if ($message) {
                foreach ($message->getEvents() as $event) {
                    $allEvents[] = $event;
                }
            }
        }
        
        if (empty($allEvents)) {
            return null;
        }
        
        // Sort by occurredAt DESC, then by ID DESC for deterministic ordering
        usort($allEvents, function($a, $b) {
            $timeComparison = $b->getOccurredAt() <=> $a->getOccurredAt();
            if ($timeComparison === 0) {
                // If times are equal, use ID as tiebreaker (newer ID = later event)
                return $b->getId() <=> $a->getId();
            }
            return $timeComparison;
        });
        
        $lastEvent = $allEvents[0];
        
        return [
            'type' => $lastEvent->getEventType(),
            'occurred_at' => $lastEvent->getOccurredAt(),
            'metadata' => $lastEvent->getEventData(),
            'message_id' => $lastEvent->getMessage()->getId()->toRfc4122(),
        ];
    }
}
