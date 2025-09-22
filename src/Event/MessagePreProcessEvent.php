<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Event;

use Nkamuo\NotificationTrackerBundle\Entity\Message;

/**
 * Event dispatched before processing a message
 * This allows for preprocessing and modification
 */
class MessagePreProcessEvent extends MessageEvent
{
    public const NAME = 'notification_tracker.message.pre_process';

    private bool $cancelProcessing = false;

    public function cancelProcessing(): void
    {
        $this->cancelProcessing = true;
    }

    public function shouldCancelProcessing(): bool
    {
        return $this->cancelProcessing;
    }
}
