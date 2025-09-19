<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Message;

use Symfony\Component\Uid\Ulid;

class TrackEmailMessage
{
    public function __construct(
        private readonly Ulid $messageId,
        private readonly string $event,
        private readonly array $data = []
    ) {
    }

    public function getMessageId(): Ulid
    {
        return $this->messageId;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
