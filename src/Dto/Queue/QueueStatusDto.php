<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Dto\Queue;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Nkamuo\NotificationTrackerBundle\State\Queue\QueueStatusProvider;

#[ApiResource(
    shortName: 'QueueStatus',
    operations: [
        new Get(
            uriTemplate: '/messages/queue/status',
            provider: QueueStatusProvider::class
        ),
    ],
    routePrefix: '/notification-tracker'
)]
class QueueStatusDto
{
    public function __construct(
        public readonly array $queues = [],
        public readonly array $summary = [],
        public readonly array $workers = [],
        public readonly array $health = []
    ) {
    }
}
