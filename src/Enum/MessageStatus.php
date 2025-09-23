<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Enum;

/**
 * Enumeration for message status values.
 * 
 * Defines the lifecycle states of individual messages:
 * - PENDING: Message created but not yet processed
 * - QUEUED: Queued for delivery by transport layer
 * - SENDING: Currently being sent through the transport
 * - SENT: Successfully handed off to transport
 * - DELIVERED: Confirmed delivery to recipient
 * - FAILED: Delivery failed permanently
 * - BOUNCED: Message bounced back from recipient
 * - CANCELLED: Message was cancelled before sending
 * - RETRYING: Message failed but is being retried
 */
enum MessageStatus: string
{
    case PENDING = 'pending';
    case QUEUED = 'queued';
    case SENDING = 'sending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case BOUNCED = 'bounced';
    case CANCELLED = 'cancelled';
    case RETRYING = 'retrying';

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
            self::PENDING => 'Pending',
            self::QUEUED => 'Queued',
            self::SENDING => 'Sending',
            self::SENT => 'Sent',
            self::DELIVERED => 'Delivered',
            self::FAILED => 'Failed',
            self::BOUNCED => 'Bounced',
            self::CANCELLED => 'Cancelled',
            self::RETRYING => 'Retrying',
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
            self::PENDING => 'Message created but not yet processed',
            self::QUEUED => 'Queued for delivery by transport layer',
            self::SENDING => 'Currently being sent through the transport',
            self::SENT => 'Successfully handed off to transport',
            self::DELIVERED => 'Confirmed delivery to recipient',
            self::FAILED => 'Delivery failed permanently',
            self::BOUNCED => 'Message bounced back from recipient',
            self::CANCELLED => 'Message was cancelled before sending',
            self::RETRYING => 'Message failed but is being retried',
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
            self::PENDING => 'gray',
            self::QUEUED => 'blue',
            self::SENDING => 'orange',
            self::SENT => 'green',
            self::DELIVERED => 'emerald',
            self::FAILED => 'red',
            self::BOUNCED => 'amber',
            self::CANCELLED => 'purple',
            self::RETRYING => 'yellow',
        };
    }

    /**
     * Check if status indicates the message is active/processable
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return in_array($this, [self::PENDING, self::QUEUED, self::SENDING, self::RETRYING]);
    }

    /**
     * Check if status indicates the message is completed
     * 
     * @return bool
     */
    public function isCompleted(): bool
    {
        return in_array($this, [self::SENT, self::DELIVERED, self::FAILED, self::BOUNCED, self::CANCELLED]);
    }

    /**
     * Check if status indicates successful delivery
     * 
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return in_array($this, [self::SENT, self::DELIVERED]);
    }

    /**
     * Check if status indicates failure
     * 
     * @return bool
     */
    public function isFailed(): bool
    {
        return in_array($this, [self::FAILED, self::BOUNCED]);
    }

    /**
     * Check if status can be retried
     * 
     * @return bool
     */
    public function canBeRetried(): bool
    {
        return in_array($this, [self::FAILED, self::BOUNCED]);
    }

    /**
     * Get the next logical status in the workflow
     * 
     * @return self|null
     */
    public function getNextStatus(): ?self
    {
        return match($this) {
            self::PENDING => self::QUEUED,
            self::QUEUED => self::SENDING,
            self::SENDING => self::SENT,
            self::SENT => self::DELIVERED,
            self::RETRYING => self::SENDING,
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
            self::PENDING => [self::QUEUED, self::CANCELLED],
            self::QUEUED => [self::SENDING, self::CANCELLED],
            self::SENDING => [self::SENT, self::FAILED, self::BOUNCED],
            self::SENT => [self::DELIVERED, self::FAILED],
            self::FAILED => [self::RETRYING],
            self::BOUNCED => [self::RETRYING],
            self::RETRYING => [self::SENDING, self::FAILED],
            default => [],
        };
    }
}
