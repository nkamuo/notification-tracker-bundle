<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Enum;

/**
 * Enumeration for notification status values.
 * 
 * Defines the lifecycle states of notifications:
 * - DRAFT: Created but not yet scheduled or sent
 * - SCHEDULED: Queued for future delivery at a specific time
 * - QUEUED: Ready for immediate processing by message handlers
 * - SENDING: Currently being processed and sent to recipients
 * - SENT: Successfully delivered to all recipients
 * - FAILED: Delivery failed and cannot be retried
 * - CANCELLED: Manually cancelled before or during delivery
 */
enum NotificationStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case QUEUED = 'queued';
    case SENDING = 'sending';
    case SENT = 'sent';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    /**
     * Get all valid status values as an array
     * 
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }

    /**
     * Get display label for the status
     * 
     * @return string
     */
    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::SCHEDULED => 'Scheduled',
            self::QUEUED => 'Queued',
            self::SENDING => 'Sending',
            self::SENT => 'Sent',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Get description for the status
     * 
     * @return string
     */
    public function getDescription(): string
    {
        return match($this) {
            self::DRAFT => 'Created but not yet scheduled or sent',
            self::SCHEDULED => 'Queued for future delivery at a specific time',
            self::QUEUED => 'Ready for immediate processing by message handlers',
            self::SENDING => 'Currently being processed and sent to recipients',
            self::SENT => 'Successfully delivered to all recipients',
            self::FAILED => 'Delivery failed and cannot be retried',
            self::CANCELLED => 'Manually cancelled before or during delivery',
        };
    }

    /**
     * Get the color associated with this status for UI display
     * 
     * @return string
     */
    public function getColor(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::SCHEDULED => 'blue',
            self::QUEUED => 'yellow',
            self::SENDING => 'orange',
            self::SENT => 'green',
            self::FAILED => 'red',
            self::CANCELLED => 'purple',
        };
    }

    /**
     * Check if status indicates the notification is active/processable
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return in_array($this, [self::DRAFT, self::SCHEDULED, self::QUEUED, self::SENDING]);
    }

    /**
     * Check if status indicates the notification is completed
     * 
     * @return bool
     */
    public function isCompleted(): bool
    {
        return in_array($this, [self::SENT, self::FAILED, self::CANCELLED]);
    }

    /**
     * Check if status indicates the notification can be edited
     * 
     * @return bool
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::SCHEDULED]);
    }

    /**
     * Check if status indicates the notification can be sent immediately
     * 
     * @return bool
     */
    public function canBeSent(): bool
    {
        return in_array($this, [self::DRAFT, self::SCHEDULED]);
    }

    /**
     * Check if status indicates the notification can be cancelled
     * 
     * @return bool
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [self::DRAFT, self::SCHEDULED, self::QUEUED]);
    }

    /**
     * Check if status indicates the notification is in draft state
     * 
     * @return bool
     */
    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Get the next logical status in the workflow
     * 
     * @return self|null
     */
    public function getNextStatus(): ?self
    {
        return match($this) {
            self::DRAFT => self::QUEUED,
            self::SCHEDULED => self::QUEUED,
            self::QUEUED => self::SENDING,
            self::SENDING => self::SENT,
            default => null,
        };
    }

    /**
     * Get all valid transition statuses from current status
     * 
     * @return array<self>
     */
    public function getValidTransitions(): array
    {
        return match($this) {
            self::DRAFT => [self::SCHEDULED, self::QUEUED, self::CANCELLED],
            self::SCHEDULED => [self::QUEUED, self::CANCELLED],
            self::QUEUED => [self::SENDING, self::CANCELLED],
            self::SENDING => [self::SENT, self::FAILED],
            default => [],
        };
    }
}
