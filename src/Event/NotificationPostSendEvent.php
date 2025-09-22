<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Event;

use Nkamuo\NotificationTrackerBundle\Entity\Notification;

/**
 * Event dispatched after a notification has been sent
 * This allows for post-send operations and logging
 */
class NotificationPostSendEvent extends NotificationEvent
{
    public const NAME = 'notification_tracker.notification.post_send';

    public function __construct(
        Notification $notification,
        private bool $success,
        private ?string $errorMessage = null,
        array $context = []
    ) {
        parent::__construct($notification, $context);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
