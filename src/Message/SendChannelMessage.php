<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Message;

use Symfony\Component\Uid\Ulid;

/**
 * Message to send an individual channel message
 */
class SendChannelMessage
{
    public function __construct(
        private readonly Ulid $messageId,
        private readonly string $channel,
        private readonly ?array $recipientData = null,
        private readonly ?array $metadata = null
    ) {
    }

    public function getMessageId(): Ulid
    {
        return $this->messageId;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getRecipientData(): ?array
    {
        return $this->recipientData;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }
}
