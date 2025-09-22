<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Event;

use Nkamuo\NotificationTrackerBundle\Entity\Message;

/**
 * Event dispatched after a message has been processed
 * This allows for post-processing operations
 */
class MessagePostProcessEvent extends MessageEvent
{
    public const NAME = 'notification_tracker.message.post_process';

    public function __construct(
        Message $message,
        private bool $success,
        private ?string $errorMessage = null,
        array $context = []
    ) {
        parent::__construct($message, $context);
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
