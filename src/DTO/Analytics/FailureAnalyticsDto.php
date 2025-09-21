<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\DTO\Analytics;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Nkamuo\NotificationTrackerBundle\State\Analytics\FailureAnalyticsProvider;

#[ApiResource(
    shortName: 'FailureAnalytics',
    operations: [
        new Get(
            uriTemplate: '/analytics/failures',
            provider: FailureAnalyticsProvider::class
        ),
    ],
    routePrefix: '/notification-tracker'
)]
class FailureAnalyticsDto
{
    public function __construct(
        public readonly string $period = '30d',
        public readonly ?string $channel = null,
        public readonly string $groupBy = 'reason',
        public readonly array $failures = [],
        public readonly array $patterns = [],
        public readonly array $recommendations = [],
        public readonly array $trends = []
    ) {
    }
}
