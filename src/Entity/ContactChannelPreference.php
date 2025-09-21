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

#[ORM\Entity(repositoryClass: 'Nkamuo\NotificationTrackerBundle\Repository\ContactChannelPreferenceRepository')]
#[ORM\Table(name: 'nt_contact_channel_preference')]
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
    normalizationContext: ['groups' => ['contact_channel_preference:read']],
    denormalizationContext: ['groups' => ['contact_channel_preference:write']]
)]
class ContactChannelPreference
{
    public const FREQUENCY_IMMEDIATE = 'immediate';
    public const FREQUENCY_HOURLY = 'hourly';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_NEVER = 'never';

    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_ALL = 'all';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    #[Groups(['contact_channel_preference:read'])]
    private string $id;

    #[ORM\OneToOne(targetEntity: ContactChannel::class, inversedBy: 'preference')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private ContactChannel $contactChannel;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private bool $allowNotifications = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private bool $allowTransactional = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private bool $allowMarketing = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private bool $allowPromotional = false;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice([
        self::FREQUENCY_IMMEDIATE,
        self::FREQUENCY_HOURLY,
        self::FREQUENCY_DAILY,
        self::FREQUENCY_WEEKLY,
        self::FREQUENCY_MONTHLY,
        self::FREQUENCY_NEVER
    ])]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private string $frequency = self::FREQUENCY_IMMEDIATE;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice([
        self::PRIORITY_HIGH,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_LOW,
        self::PRIORITY_ALL
    ])]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private string $minimumPriority = self::PRIORITY_ALL;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private ?array $quietHours = null; // Format: [{'start': '22:00', 'end': '08:00', 'timezone': 'UTC', 'days': ['mon', 'tue']}]

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private ?array $allowedCategories = null; // Categories of notifications allowed

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private ?array $blockedCategories = null; // Categories of notifications blocked

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private ?array $allowedSenders = null; // Specific senders allowed

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private ?array $blockedSenders = null; // Specific senders blocked

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 100)]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private ?int $maxMessagesPerHour = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 1000)]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private ?int $maxMessagesPerDay = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 7000)]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private ?int $maxMessagesPerWeek = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private bool $requireDoubleOptIn = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private bool $allowAutoUnsubscribe = true;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact_channel_preference:read', 'contact_channel_preference:write'])]
    private ?array $customRules = null; // Custom preference rules

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['contact_channel_preference:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['contact_channel_preference:read'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['contact_channel_preference:read'])]
    private ?\DateTimeImmutable $lastOptInAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['contact_channel_preference:read'])]
    private ?\DateTimeImmutable $lastOptOutAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['contact_channel_preference:read'])]
    private ?string $optOutReason = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['contact_channel_preference:read'])]
    private int $messagesThisHour = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['contact_channel_preference:read'])]
    private int $messagesToday = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['contact_channel_preference:read'])]
    private int $messagesThisWeek = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['contact_channel_preference:read'])]
    private ?\DateTimeImmutable $lastMessageAt = null;

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

    public function canReceiveMessage(
        string $category = null,
        string $priority = null,
        string $sender = null,
        \DateTimeInterface $sendAt = null
    ): bool {
        // Check if notifications are allowed at all
        if (!$this->allowNotifications) {
            return false;
        }

        // Check frequency limits
        if (!$this->checkFrequencyLimits()) {
            return false;
        }

        // Check rate limits
        if (!$this->checkRateLimits()) {
            return false;
        }

        // Check priority
        if ($priority && !$this->checkPriority($priority)) {
            return false;
        }

        // Check category
        if ($category && !$this->checkCategory($category)) {
            return false;
        }

        // Check sender
        if ($sender && !$this->checkSender($sender)) {
            return false;
        }

        // Check quiet hours
        if ($sendAt && !$this->checkQuietHours($sendAt)) {
            return false;
        }

        return true;
    }

    private function checkFrequencyLimits(): bool
    {
        switch ($this->frequency) {
            case self::FREQUENCY_NEVER:
                return false;
            case self::FREQUENCY_IMMEDIATE:
                return true;
            case self::FREQUENCY_HOURLY:
                return !$this->lastMessageAt || 
                    $this->lastMessageAt <= new \DateTimeImmutable('-1 hour');
            case self::FREQUENCY_DAILY:
                return !$this->lastMessageAt || 
                    $this->lastMessageAt <= new \DateTimeImmutable('-1 day');
            case self::FREQUENCY_WEEKLY:
                return !$this->lastMessageAt || 
                    $this->lastMessageAt <= new \DateTimeImmutable('-1 week');
            case self::FREQUENCY_MONTHLY:
                return !$this->lastMessageAt || 
                    $this->lastMessageAt <= new \DateTimeImmutable('-1 month');
            default:
                return true;
        }
    }

    private function checkRateLimits(): bool
    {
        if ($this->maxMessagesPerHour && $this->messagesThisHour >= $this->maxMessagesPerHour) {
            return false;
        }
        if ($this->maxMessagesPerDay && $this->messagesToday >= $this->maxMessagesPerDay) {
            return false;
        }
        if ($this->maxMessagesPerWeek && $this->messagesThisWeek >= $this->maxMessagesPerWeek) {
            return false;
        }
        return true;
    }

    private function checkPriority(string $priority): bool
    {
        $priorities = [
            self::PRIORITY_HIGH => 3,
            self::PRIORITY_MEDIUM => 2,
            self::PRIORITY_LOW => 1,
        ];

        $minLevel = $priorities[$this->minimumPriority] ?? 0;
        $messageLevel = $priorities[$priority] ?? 0;

        return $messageLevel >= $minLevel;
    }

    private function checkCategory(string $category): bool
    {
        // Check blocked categories first
        if ($this->blockedCategories && in_array($category, $this->blockedCategories)) {
            return false;
        }

        // If allowed categories is set, check if category is in the list
        if ($this->allowedCategories) {
            return in_array($category, $this->allowedCategories);
        }

        return true;
    }

    private function checkSender(string $sender): bool
    {
        // Check blocked senders first
        if ($this->blockedSenders && in_array($sender, $this->blockedSenders)) {
            return false;
        }

        // If allowed senders is set, check if sender is in the list
        if ($this->allowedSenders) {
            return in_array($sender, $this->allowedSenders);
        }

        return true;
    }

    private function checkQuietHours(\DateTimeInterface $sendAt): bool
    {
        if (!$this->quietHours) {
            return true;
        }

        foreach ($this->quietHours as $quietPeriod) {
            if ($this->isInQuietPeriod($sendAt, $quietPeriod)) {
                return false;
            }
        }

        return true;
    }

    private function isInQuietPeriod(\DateTimeInterface $sendAt, array $quietPeriod): bool
    {
        // Convert to the timezone specified in the quiet period
        $timezone = new \DateTimeZone($quietPeriod['timezone'] ?? 'UTC');
        $sendTime = \DateTime::createFromInterface($sendAt)->setTimezone($timezone);
        
        // Check if it's a quiet day
        $dayOfWeek = strtolower($sendTime->format('D'));
        if (isset($quietPeriod['days']) && !in_array($dayOfWeek, $quietPeriod['days'])) {
            return false;
        }

        // Check if it's in quiet hours
        $currentTime = $sendTime->format('H:i');
        $startTime = $quietPeriod['start'];
        $endTime = $quietPeriod['end'];

        // Handle overnight quiet periods (e.g., 22:00 to 08:00)
        if ($startTime > $endTime) {
            return $currentTime >= $startTime || $currentTime <= $endTime;
        } else {
            return $currentTime >= $startTime && $currentTime <= $endTime;
        }
    }

    public function recordMessage(): void
    {
        $now = new \DateTimeImmutable();
        $this->lastMessageAt = $now;

        // Reset counters if needed
        $this->resetCountersIfNeeded($now);

        // Increment counters
        $this->messagesThisHour++;
        $this->messagesToday++;
        $this->messagesThisWeek++;
    }

    private function resetCountersIfNeeded(\DateTimeImmutable $now): void
    {
        if (!$this->lastMessageAt) {
            return;
        }

        // Reset hourly counter
        if ($this->lastMessageAt <= $now->modify('-1 hour')) {
            $this->messagesThisHour = 0;
        }

        // Reset daily counter
        if ($this->lastMessageAt->format('Y-m-d') !== $now->format('Y-m-d')) {
            $this->messagesToday = 0;
        }

        // Reset weekly counter
        if ($this->lastMessageAt <= $now->modify('-1 week')) {
            $this->messagesThisWeek = 0;
        }
    }

    public function optIn(string $reason = null): void
    {
        $this->allowNotifications = true;
        $this->lastOptInAt = new \DateTimeImmutable();
        $this->lastOptOutAt = null;
        $this->optOutReason = null;
    }

    public function optOut(string $reason = null): void
    {
        $this->allowNotifications = false;
        $this->lastOptOutAt = new \DateTimeImmutable();
        $this->optOutReason = $reason;
    }

    public function addQuietHours(string $start, string $end, string $timezone = 'UTC', array $days = []): self
    {
        $quietHours = $this->quietHours ?? [];
        $quietHours[] = [
            'start' => $start,
            'end' => $end,
            'timezone' => $timezone,
            'days' => $days ?: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']
        ];
        $this->quietHours = $quietHours;
        return $this;
    }

    public function removeQuietHours(int $index): self
    {
        $quietHours = $this->quietHours ?? [];
        if (isset($quietHours[$index])) {
            unset($quietHours[$index]);
            $this->quietHours = array_values($quietHours);
        }
        return $this;
    }

    public function allowCategory(string $category): self
    {
        $allowed = $this->allowedCategories ?? [];
        if (!in_array($category, $allowed)) {
            $allowed[] = $category;
            $this->allowedCategories = $allowed;
        }
        return $this;
    }

    public function blockCategory(string $category): self
    {
        $blocked = $this->blockedCategories ?? [];
        if (!in_array($category, $blocked)) {
            $blocked[] = $category;
            $this->blockedCategories = $blocked;
        }
        return $this;
    }

    public function allowSender(string $sender): self
    {
        $allowed = $this->allowedSenders ?? [];
        if (!in_array($sender, $allowed)) {
            $allowed[] = $sender;
            $this->allowedSenders = $allowed;
        }
        return $this;
    }

    public function blockSender(string $sender): self
    {
        $blocked = $this->blockedSenders ?? [];
        if (!in_array($sender, $blocked)) {
            $blocked[] = $sender;
            $this->blockedSenders = $blocked;
        }
        return $this;
    }

    // Getters and Setters
    public function getId(): string
    {
        return $this->id;
    }

    public function getContactChannel(): ContactChannel
    {
        return $this->contactChannel;
    }

    public function setContactChannel(ContactChannel $contactChannel): self
    {
        $this->contactChannel = $contactChannel;
        return $this;
    }

    public function getAllowNotifications(): bool
    {
        return $this->allowNotifications;
    }

    public function setAllowNotifications(bool $allowNotifications): self
    {
        $this->allowNotifications = $allowNotifications;
        return $this;
    }

    public function getAllowTransactional(): bool
    {
        return $this->allowTransactional;
    }

    public function setAllowTransactional(bool $allowTransactional): self
    {
        $this->allowTransactional = $allowTransactional;
        return $this;
    }

    public function getAllowMarketing(): bool
    {
        return $this->allowMarketing;
    }

    public function setAllowMarketing(bool $allowMarketing): self
    {
        $this->allowMarketing = $allowMarketing;
        return $this;
    }

    public function getAllowPromotional(): bool
    {
        return $this->allowPromotional;
    }

    public function setAllowPromotional(bool $allowPromotional): self
    {
        $this->allowPromotional = $allowPromotional;
        return $this;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): self
    {
        $this->frequency = $frequency;
        return $this;
    }

    public function getMinimumPriority(): string
    {
        return $this->minimumPriority;
    }

    public function setMinimumPriority(string $minimumPriority): self
    {
        $this->minimumPriority = $minimumPriority;
        return $this;
    }

    public function getQuietHours(): ?array
    {
        return $this->quietHours;
    }

    public function setQuietHours(?array $quietHours): self
    {
        $this->quietHours = $quietHours;
        return $this;
    }

    public function getAllowedCategories(): ?array
    {
        return $this->allowedCategories;
    }

    public function setAllowedCategories(?array $allowedCategories): self
    {
        $this->allowedCategories = $allowedCategories;
        return $this;
    }

    public function getBlockedCategories(): ?array
    {
        return $this->blockedCategories;
    }

    public function setBlockedCategories(?array $blockedCategories): self
    {
        $this->blockedCategories = $blockedCategories;
        return $this;
    }

    public function getAllowedSenders(): ?array
    {
        return $this->allowedSenders;
    }

    public function setAllowedSenders(?array $allowedSenders): self
    {
        $this->allowedSenders = $allowedSenders;
        return $this;
    }

    public function getBlockedSenders(): ?array
    {
        return $this->blockedSenders;
    }

    public function setBlockedSenders(?array $blockedSenders): self
    {
        $this->blockedSenders = $blockedSenders;
        return $this;
    }

    public function getMaxMessagesPerHour(): ?int
    {
        return $this->maxMessagesPerHour;
    }

    public function setMaxMessagesPerHour(?int $maxMessagesPerHour): self
    {
        $this->maxMessagesPerHour = $maxMessagesPerHour;
        return $this;
    }

    public function getMaxMessagesPerDay(): ?int
    {
        return $this->maxMessagesPerDay;
    }

    public function setMaxMessagesPerDay(?int $maxMessagesPerDay): self
    {
        $this->maxMessagesPerDay = $maxMessagesPerDay;
        return $this;
    }

    public function getMaxMessagesPerWeek(): ?int
    {
        return $this->maxMessagesPerWeek;
    }

    public function setMaxMessagesPerWeek(?int $maxMessagesPerWeek): self
    {
        $this->maxMessagesPerWeek = $maxMessagesPerWeek;
        return $this;
    }

    public function getRequireDoubleOptIn(): bool
    {
        return $this->requireDoubleOptIn;
    }

    public function setRequireDoubleOptIn(bool $requireDoubleOptIn): self
    {
        $this->requireDoubleOptIn = $requireDoubleOptIn;
        return $this;
    }

    public function getAllowAutoUnsubscribe(): bool
    {
        return $this->allowAutoUnsubscribe;
    }

    public function setAllowAutoUnsubscribe(bool $allowAutoUnsubscribe): self
    {
        $this->allowAutoUnsubscribe = $allowAutoUnsubscribe;
        return $this;
    }

    public function getCustomRules(): ?array
    {
        return $this->customRules;
    }

    public function setCustomRules(?array $customRules): self
    {
        $this->customRules = $customRules;
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

    public function getLastOptInAt(): ?\DateTimeImmutable
    {
        return $this->lastOptInAt;
    }

    public function setLastOptInAt(?\DateTimeImmutable $lastOptInAt): self
    {
        $this->lastOptInAt = $lastOptInAt;
        return $this;
    }

    public function getLastOptOutAt(): ?\DateTimeImmutable
    {
        return $this->lastOptOutAt;
    }

    public function setLastOptOutAt(?\DateTimeImmutable $lastOptOutAt): self
    {
        $this->lastOptOutAt = $lastOptOutAt;
        return $this;
    }

    public function getOptOutReason(): ?string
    {
        return $this->optOutReason;
    }

    public function setOptOutReason(?string $optOutReason): self
    {
        $this->optOutReason = $optOutReason;
        return $this;
    }

    public function getMessagesThisHour(): int
    {
        return $this->messagesThisHour;
    }

    public function getMessagesToday(): int
    {
        return $this->messagesToday;
    }

    public function getMessagesThisWeek(): int
    {
        return $this->messagesThisWeek;
    }

    public function getLastMessageAt(): ?\DateTimeImmutable
    {
        return $this->lastMessageAt;
    }

    /**
     * Check if messages are allowed considering all rules
     */
    public function isMessagesAllowed(?string $senderId = null): bool
    {
        if (!$this->allowNotifications) {
            return false;
        }

        // Check frequency
        if ($this->frequency === self::FREQUENCY_NEVER) {
            return false;
        }

        // Check quiet hours
        if ($this->isInQuietHours()) {
            return false;
        }

        // Check frequency limits
        if (!$this->isWithinFrequencyLimits()) {
            return false;
        }

        return true;
    }

    /**
     * Check if current time is within quiet hours
     */
    public function isInQuietHours(): bool
    {
        $quietHours = $this->getQuietHours();
        
        if (empty($quietHours)) {
            return false;
        }

        $now = new \DateTimeImmutable();
        $currentTime = $now->format('H:i');
        $currentDay = strtolower($now->format('D')); // mon, tue, etc.

        foreach ($quietHours as $period) {
            $startTime = $period['start'] ?? null;
            $endTime = $period['end'] ?? null;
            $days = $period['days'] ?? [];

            if (!$startTime || !$endTime) {
                continue;
            }

            // Check if today is in the restricted days
            if (!empty($days) && !in_array($currentDay, $days)) {
                continue;
            }

            // Handle overnight quiet hours (e.g., 22:00 to 06:00)
            if ($startTime > $endTime) {
                if ($currentTime >= $startTime || $currentTime <= $endTime) {
                    return true;
                }
            } else {
                if ($currentTime >= $startTime && $currentTime <= $endTime) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if within frequency limits
     */
    public function isWithinFrequencyLimits(): bool
    {
        switch ($this->frequency) {
            case self::FREQUENCY_NEVER:
                return false;
            case self::FREQUENCY_IMMEDIATE:
                return true;
            case self::FREQUENCY_HOURLY:
                return $this->messagesThisHour < $this->calculateMaxMessagesPerHour();
            case self::FREQUENCY_DAILY:
                return $this->messagesToday < $this->calculateMaxMessagesPerDay();
            case self::FREQUENCY_WEEKLY:
                return $this->messagesThisWeek < $this->calculateMaxMessagesPerWeek();
            case self::FREQUENCY_MONTHLY:
                return $this->getMessagesThisMonth() < $this->getMaxMessagesPerMonth();
            default:
                return true;
        }
    }

    /**
     * Record a message being sent
     */
    public function recordMessageSent(): void
    {
        // This would need to be implemented with proper tracking
        // For now, we'll just mark it as a placeholder
    }

    /**
     * Reset rate limit counters
     */
    public function resetRateLimit(): void
    {
        // This would need to be implemented with proper rate limit tracking
        // For now, we'll just mark it as a placeholder
    }

    /**
     * Calculate max messages per hour based on frequency setting
     */
    private function calculateMaxMessagesPerHour(): int
    {
        switch ($this->frequency) {
            case self::FREQUENCY_HOURLY:
                return 1;
            case self::FREQUENCY_DAILY:
                return 24;
            case self::FREQUENCY_IMMEDIATE:
                return PHP_INT_MAX;
            default:
                return 0;
        }
    }

    /**
     * Calculate max messages per day based on frequency setting
     */
    private function calculateMaxMessagesPerDay(): int
    {
        switch ($this->frequency) {
            case self::FREQUENCY_DAILY:
                return 1;
            case self::FREQUENCY_WEEKLY:
                return 7;
            case self::FREQUENCY_IMMEDIATE:
                return PHP_INT_MAX;
            case self::FREQUENCY_HOURLY:
                return 24;
            default:
                return 0;
        }
    }

    /**
     * Calculate max messages per week based on frequency setting
     */
    private function calculateMaxMessagesPerWeek(): int
    {
        switch ($this->frequency) {
            case self::FREQUENCY_WEEKLY:
                return 1;
            case self::FREQUENCY_MONTHLY:
                return 4;
            case self::FREQUENCY_IMMEDIATE:
                return PHP_INT_MAX;
            case self::FREQUENCY_HOURLY:
                return 168;
            case self::FREQUENCY_DAILY:
                return 7;
            default:
                return 0;
        }
    }

    /**
     * Get max messages per month based on frequency setting
     */
    private function getMaxMessagesPerMonth(): int
    {
        switch ($this->frequency) {
            case self::FREQUENCY_MONTHLY:
                return 1;
            case self::FREQUENCY_IMMEDIATE:
                return PHP_INT_MAX;
            case self::FREQUENCY_HOURLY:
                return 744; // ~31 days * 24 hours
            case self::FREQUENCY_DAILY:
                return 31;
            case self::FREQUENCY_WEEKLY:
                return 4;
            default:
                return 0;
        }
    }

    /**
     * Get messages this month
     */
    private function getMessagesThisMonth(): int
    {
        // This would need to be implemented with proper tracking
        return 0;
    }
}
