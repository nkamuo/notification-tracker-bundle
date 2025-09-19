<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Message;

use Symfony\Component\Uid\Ulid;

class ProcessWebhookMessage
{
    public function __construct(
        private readonly Ulid $webhookId,
        private readonly string $provider,
        private readonly array $payload,
        private readonly array $headers = []
    ) {
    }

    public function getWebhookId(): Ulid
    {
        return $this->webhookId;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}