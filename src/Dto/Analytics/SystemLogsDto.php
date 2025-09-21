<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Dto\Analytics;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Nkamuo\NotificationTrackerBundle\State\Analytics\SystemLogsProvider;

#[ApiResource(
    shortName: 'SystemLogs',
    operations: [
        new GetCollection(
            uriTemplate: '/analytics/logs',
            provider: SystemLogsProvider::class
        ),
    ],
    routePrefix: '/notification-tracker'
)]
class SystemLogsDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $timestamp,
        public readonly string $level,
        public readonly string $message,
        public readonly ?string $channel = null,
        public readonly array $context = [],
        public readonly array $metadata = []
    ) {
    }
}
