<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Stamp to uniquely identify notification messages across retries and transports.
 */
final class NotificationTrackingStamp implements StampInterface
{
    public function __construct(
        public readonly string $id
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
