<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Message;

use Symfony\Component\Uid\Ulid;

/**
 * Message to send a notification through the messenger system
 */
class SendNotificationMessage
{
    public function __construct(
        private readonly Ulid $notificationId,
        private readonly ?string $channel = null,
        private readonly ?array $recipientOverrides = null,
        private readonly ?array $metadata = null
    ) {
    }

    public function getNotificationId(): Ulid
    {
        return $this->notificationId;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function getRecipientOverrides(): ?array
    {
        return $this->recipientOverrides;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }
}
