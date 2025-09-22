<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Event;

use Nkamuo\NotificationTrackerBundle\Entity\Message;

/**
 * Event dispatched when processing inbound messages (webhooks, etc.)
 * This allows for custom parsing and enrichment
 */
class InboundMessageEvent extends MessageEvent
{
    public const NAME = 'notification_tracker.message.inbound';

    public function __construct(
        Message $message,
        private array $rawData,
        private string $provider,
        array $context = []
    ) {
        parent::__construct($message, $context);
    }

    public function getRawData(): array
    {
        return $this->rawData;
    }

    public function setRawData(array $rawData): self
    {
        $this->rawData = $rawData;
        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }
}
