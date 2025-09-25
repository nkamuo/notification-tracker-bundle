<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Enum;

/**
 * Enumeration for notification importance levels.
 */
enum NotificationImportance: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';

    /**
     * Get all valid importance values as an array
     * 
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }

    /**
     * Get display label for the importance level
     * 
     * @return string
     */
    public function getLabel(): string
    {
        return match($this) {
            self::LOW => 'Low',
            self::NORMAL => 'Normal',
            self::HIGH => 'High',
            self::URGENT => 'Urgent',
        };
    }

    /**
     * Get description for the importance level
     * 
     * @return string
     */
    public function getDescription(): string
    {
        return match($this) {
            self::LOW => 'Low priority notification',
            self::NORMAL => 'Normal priority notification',
            self::HIGH => 'High priority notification',
            self::URGENT => 'Urgent notification requiring immediate attention',
        };
    }

    /**
     * Get numeric priority value (higher = more important)
     * 
     * @return int
     */
    public function getPriority(): int
    {
        return match($this) {
            self::LOW => 1,
            self::NORMAL => 2,
            self::HIGH => 3,
            self::URGENT => 4,
        };
    }
}
