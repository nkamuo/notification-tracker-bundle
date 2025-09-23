<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Enum;

/**
 * Enumeration for notification and message direction types.
 * 
 * Defines the flow direction of notifications and messages:
 * - INBOUND: Messages/notifications received from external sources (webhooks, replies, etc.)
 * - OUTBOUND: Messages/notifications being sent to external recipients
 * - DRAFT: Notifications in draft state, not yet sent or scheduled
 */
enum NotificationDirection: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
    case DRAFT = 'draft';

    /**
     * Get all valid direction values as an array
     * 
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }

    /**
     * Get display label for the direction
     * 
     * @return string
     */
    public function getLabel(): string
    {
        return match($this) {
            self::INBOUND => 'Inbound',
            self::OUTBOUND => 'Outbound', 
            self::DRAFT => 'Draft',
        };
    }

    /**
     * Get description for the direction
     * 
     * @return string
     */
    public function getDescription(): string
    {
        return match($this) {
            self::INBOUND => 'Messages received from external sources',
            self::OUTBOUND => 'Messages sent to external recipients',
            self::DRAFT => 'Draft notifications not yet sent',
        };
    }

    /**
     * Check if direction is for outgoing messages
     * 
     * @return bool
     */
    public function isOutgoing(): bool
    {
        return $this === self::OUTBOUND;
    }

    /**
     * Check if direction is for incoming messages
     * 
     * @return bool
     */
    public function isIncoming(): bool
    {
        return $this === self::INBOUND;
    }

    /**
     * Check if direction is for draft state
     * 
     * @return bool
     */
    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }
}
