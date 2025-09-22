<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Event;

use Nkamuo\NotificationTrackerBundle\Entity\Message;

/**
 * Event dispatched when a message is created
 * This allows for enrichment of the message data
 */
class MessageCreatedEvent extends MessageEvent
{
    public const NAME = 'notification_tracker.message.created';

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
