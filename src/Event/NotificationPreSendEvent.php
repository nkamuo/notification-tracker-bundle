<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Event;

use Nkamuo\NotificationTrackerBundle\Entity\Notification;

/**
 * Event dispatched before sending a notification
 * This allows for final modifications before sending
 */
class NotificationPreSendEvent extends NotificationEvent
{
    public const NAME = 'notification_tracker.notification.pre_send';

    private bool $cancelSending = false;

    public function cancelSending(): void
    {
        $this->cancelSending = true;
    }

    public function shouldCancelSending(): bool
    {
        return $this->cancelSending;
    }
}
