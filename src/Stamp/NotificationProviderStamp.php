<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

class NotificationProviderStamp implements StampInterface
{
    public function __construct(
        private string $provider,
        private int $priority = 0
    ) {}

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
