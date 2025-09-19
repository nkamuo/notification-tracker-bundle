<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Message;

use Symfony\Component\Uid\Ulid;

class RetryFailedMessage
{
    public function __construct(
        private readonly Ulid $messageId,
        private readonly int $attempt = 1
    ) {
    }

    public function getMessageId(): Ulid
    {
        return $this->messageId;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }
}
