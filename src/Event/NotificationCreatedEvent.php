<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Event;

use Nkamuo\NotificationTrackerBundle\Entity\Notification;

/**
 * Event dispatched when a notification is created (before saving)
 * This allows for enrichment of the notification data
 */
class NotificationCreatedEvent extends NotificationEvent
{
    public const NAME = 'notification_tracker.notification.created';

    private bool $stopProcessing = false;

    public function stopProcessing(): void
    {
        $this->stopProcessing = true;
    }

    public function shouldStopProcessing(): bool
    {
        return $this->stopProcessing;
    }
}
