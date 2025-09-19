<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Event;

use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Symfony\Contracts\EventDispatcher\Event;

class MessageTrackedEvent extends Event
{
    public const NAME = 'notification_tracker.message_tracked';

    public function __construct(
        private readonly Message $message
    ) {
    }

    public function getMessage(): Message
    {
        return $this->message;
    }
}