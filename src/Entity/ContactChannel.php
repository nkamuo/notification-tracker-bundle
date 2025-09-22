<?php

namespace Nkamuo\NotificationTrackerBundle\Entity;

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

#[ORM\Entity(repositoryClass: 'Nkamuo\NotificationTrackerBundle\Repository\ContactChannelRepository')]
#[ORM\Table(name: 'nt_contact_channel')]
#[ORM\Index(columns: ['contact_id', 'type'], name: 'idx_contact_channel_type')]
#[ORM\Index(columns: ['type', 'identifier'], name: 'idx_channel_identifier')]
#[ORM\Index(columns: ['is_primary', 'type'], name: 'idx_channel_primary')]
#[ORM\Index(columns: ['is_verified'], name: 'idx_channel_verified')]
#[ORM\Index(columns: ['is_active'], name: 'idx_channel_active')]
#[ORM\UniqueConstraint(name: 'uniq_contact_channel_primary', columns: ['contact_id', 'type', 'is_primary'])]
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
    normalizationContext: ['groups' => ['contact_channel:read']],
    denormalizationContext: ['groups' => ['contact_channel:write']],
    routePrefix: '/notification-tracker',
)]
class ContactChannel
{
    public const TYPE_EMAIL = 'email';
    public const TYPE_SMS = 'sms';
    public const TYPE_TELEGRAM = 'telegram';
    public const TYPE_SLACK = 'slack';
    public const TYPE_PUSH = 'push';
    public const TYPE_WHATSAPP = 'whatsapp';
    public const TYPE_WEBHOOK = 'webhook';
    public const TYPE_DISCORD = 'discord';
    public const TYPE_TEAMS = 'teams';
    public const TYPE_VOICE = 'voice';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_VERIFICATION_PENDING = 'verification_pending';
    public const STATUS_VERIFICATION_FAILED = 'verification_failed';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_OPTED_OUT = 'opted_out';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    #[Groups(['contact_channel:read'])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Contact::class, inversedBy: 'channels')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['contact_channel:read', 'contact_channel:write'])]
    private Contact $contact;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice([
        self::TYPE_EMAIL,
        self::TYPE_SMS,
        self::TYPE_TELEGRAM,
        self::TYPE_SLACK,
        self::TYPE_PUSH,
        self::TYPE_WHATSAPP,
        self::TYPE_WEBHOOK,
        self::TYPE_DISCORD,
        self::TYPE_TEAMS,
        self::TYPE_VOICE
    ])]
    #[Groups(['contact_channel:read', 'contact_channel:write'])]
    private string $type;

    #[ORM\Column(type: 'string', length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    #[Groups(['contact_channel:read', 'contact_channel:write'])]
    private string $identifier;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['contact_channel:read', 'contact_channel:write'])]
    private ?string $label = null;

    #[ORM\Column(type: 'string', length: 30)]
    #[Assert\Choice([
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_VERIFICATION_PENDING,
        self::STATUS_VERIFICATION_FAILED,
        self::STATUS_BOUNCED,
        self::STATUS_BLOCKED,
        self::STATUS_OPTED_OUT
    ])]
    #[Groups(['contact_channel:read', 'contact_channel:write'])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['contact_channel:read', 'contact_channel:write'])]
    private bool $isPrimary = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['contact_channel:read', 'contact_channel:write'])]
    private bool $isVerified = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['contact_channel:read', 'contact_channel:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    #[Assert\Range(min: 1, max: 100)]
    #[Groups(['contact_channel:read', 'contact_channel:write'])]
    private int $priority = 1; // 1 = highest priority

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact_channel:read', 'contact_channel:write'])]
    private ?array $metadata = null; // Channel-specific data (e.g., device tokens, chat IDs)

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact_channel:read', 'contact_channel:write'])]
    private ?array $capabilities = null; // What this channel supports (rich_text, attachments, etc.)

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['contact_channel:read', 'contact_channel:write'])]
    private ?string $verificationToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['contact_channel:read'])]
    private ?\DateTimeImmutable $verificationTokenExpiresAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['contact_channel:read'])]
    private int $verificationAttempts = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['contact_channel:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['contact_channel:read'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['contact_channel:read'])]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['contact_channel:read'])]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['contact_channel:read'])]
    private ?\DateTimeImmutable $lastBouncedAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['contact_channel:read'])]
    private int $totalMessagesSent = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['contact_channel:read'])]
    private int $totalMessagesDelivered = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['contact_channel:read'])]
    private int $totalBounces = 0;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 4, options: ['default' => '1.0000'])]
    #[Groups(['contact_channel:read'])]
    private string $deliveryRate = '1.0000';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 4, options: ['default' => '0.0000'])]
    #[Groups(['contact_channel:read'])]
    private string $bounceRate = '0.0000';

    // Relationship with preferences
    #[ORM\OneToOne(mappedBy: 'contactChannel', targetEntity: ContactChannelPreference::class, cascade: ['persist', 'remove'])]
    #[Groups(['contact_channel:read'])]
    private ?ContactChannelPreference $preference = null;

    public function __construct()
    {
        $this->id = $this->generateUlid();
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

    public function getDisplayIdentifier(): string
    {
        switch ($this->type) {
            case self::TYPE_EMAIL:
                return $this->identifier;
            case self::TYPE_SMS:
            case self::TYPE_VOICE:
                return $this->formatPhoneNumber($this->identifier);
            case self::TYPE_TELEGRAM:
                return '@' . ltrim($this->identifier, '@');
            case self::TYPE_SLACK:
                return '#' . ltrim($this->identifier, '#');
            default:
                return $this->identifier;
        }
    }

    private function formatPhoneNumber(string $phone): string
    {
        // Basic phone number formatting - implement proper formatting as needed
        $phone = preg_replace('/[^\d+]/', '', $phone);
        if (strlen($phone) === 11 && substr($phone, 0, 1) === '1') {
            return sprintf('+%s (%s) %s-%s',
                substr($phone, 0, 1),
                substr($phone, 1, 3),
                substr($phone, 4, 3),
                substr($phone, 7, 4)
            );
        }
        return $phone;
    }

    public function isValidForType(): bool
    {
        switch ($this->type) {
            case self::TYPE_EMAIL:
                return filter_var($this->identifier, FILTER_VALIDATE_EMAIL) !== false;
            case self::TYPE_SMS:
            case self::TYPE_VOICE:
                return preg_match('/^\+?[1-9]\d{1,14}$/', $this->identifier);
            case self::TYPE_WEBHOOK:
                return filter_var($this->identifier, FILTER_VALIDATE_URL) !== false;
            default:
                return !empty($this->identifier);
        }
    }

    public function generateVerificationToken(): string
    {
        $this->verificationToken = bin2hex(random_bytes(32));
        $this->verificationTokenExpiresAt = new \DateTimeImmutable('+24 hours');
        return $this->verificationToken;
    }

    public function isVerificationTokenValid(string $token): bool
    {
        return $this->verificationToken === $token 
            && $this->verificationTokenExpiresAt 
            && $this->verificationTokenExpiresAt > new \DateTimeImmutable();
    }

    public function markAsVerified(): void
    {
        $this->isVerified = true;
        $this->verifiedAt = new \DateTimeImmutable();
        $this->verificationToken = null;
        $this->verificationTokenExpiresAt = null;
        $this->status = self::STATUS_ACTIVE;
    }

    public function incrementVerificationAttempts(): void
    {
        $this->verificationAttempts++;
        if ($this->verificationAttempts >= 3) {
            $this->status = self::STATUS_VERIFICATION_FAILED;
        }
    }

    public function updateDeliveryStats(bool $delivered, bool $bounced = false): void
    {
        $this->totalMessagesSent++;
        if ($delivered) {
            $this->totalMessagesDelivered++;
            $this->lastUsedAt = new \DateTimeImmutable();
        }
        if ($bounced) {
            $this->totalBounces++;
            $this->lastBouncedAt = new \DateTimeImmutable();
        }

        // Update rates
        $this->deliveryRate = $this->totalMessagesSent > 0 
            ? number_format($this->totalMessagesDelivered / $this->totalMessagesSent, 4)
            : '1.0000';
        
        $this->bounceRate = $this->totalMessagesSent > 0 
            ? number_format($this->totalBounces / $this->totalMessagesSent, 4)
            : '0.0000';

        // Auto-disable if bounce rate is too high
        if ($this->totalMessagesSent >= 10 && (float)$this->bounceRate > 0.1) {
            $this->status = self::STATUS_BOUNCED;
            $this->isActive = false;
        }
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

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? []);
    }

    public function addCapability(string $capability): self
    {
        $capabilities = $this->capabilities ?? [];
        if (!in_array($capability, $capabilities)) {
            $capabilities[] = $capability;
            $this->capabilities = $capabilities;
        }
        return $this;
    }

    // Common capability constants
    public const CAPABILITY_RICH_TEXT = 'rich_text';
    public const CAPABILITY_ATTACHMENTS = 'attachments';
    public const CAPABILITY_DELIVERY_RECEIPT = 'delivery_receipt';
    public const CAPABILITY_READ_RECEIPT = 'read_receipt';
    public const CAPABILITY_INTERACTIVE = 'interactive';
    public const CAPABILITY_REAL_TIME = 'real_time';

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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;
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

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): self
    {
        $this->isPrimary = $isPrimary;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
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

    public function getMetadataArray(): ?array
    {
        return $this->metadata;
    }

    public function setMetadataArray(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCapabilities(): ?array
    {
        return $this->capabilities;
    }

    public function setCapabilities(?array $capabilities): self
    {
        $this->capabilities = $capabilities;
        return $this;
    }

    public function getVerificationToken(): ?string
    {
        return $this->verificationToken;
    }

    public function setVerificationToken(?string $verificationToken): self
    {
        $this->verificationToken = $verificationToken;
        return $this;
    }

    public function getVerificationTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->verificationTokenExpiresAt;
    }

    public function setVerificationTokenExpiresAt(?\DateTimeImmutable $verificationTokenExpiresAt): self
    {
        $this->verificationTokenExpiresAt = $verificationTokenExpiresAt;
        return $this;
    }

    public function getVerificationAttempts(): int
    {
        return $this->verificationAttempts;
    }

    public function setVerificationAttempts(int $verificationAttempts): self
    {
        $this->verificationAttempts = $verificationAttempts;
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

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): self
    {
        $this->verifiedAt = $verifiedAt;
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

    public function getLastBouncedAt(): ?\DateTimeImmutable
    {
        return $this->lastBouncedAt;
    }

    public function setLastBouncedAt(?\DateTimeImmutable $lastBouncedAt): self
    {
        $this->lastBouncedAt = $lastBouncedAt;
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

    public function getTotalBounces(): int
    {
        return $this->totalBounces;
    }

    public function setTotalBounces(int $totalBounces): self
    {
        $this->totalBounces = $totalBounces;
        return $this;
    }

    public function getDeliveryRate(): string
    {
        return $this->deliveryRate;
    }

    public function setDeliveryRate(string $deliveryRate): self
    {
        $this->deliveryRate = $deliveryRate;
        return $this;
    }

    public function getBounceRate(): string
    {
        return $this->bounceRate;
    }

    public function setBounceRate(string $bounceRate): self
    {
        $this->bounceRate = $bounceRate;
        return $this;
    }

    public function getPreference(): ?ContactChannelPreference
    {
        return $this->preference;
    }

    public function setPreference(?ContactChannelPreference $preference): self
    {
        $this->preference = $preference;
        if ($preference && $preference->getContactChannel() !== $this) {
            $preference->setContactChannel($this);
        }
        return $this;
    }
}
