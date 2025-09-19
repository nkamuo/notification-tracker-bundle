<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Dto\Analytics;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Nkamuo\NotificationTrackerBundle\State\Analytics\DetailedAnalyticsProvider;

#[ApiResource(
    shortName: 'DetailedAnalytics',
    operations: [
        new Get(
            uriTemplate: '/analytics/detailed',
            provider: DetailedAnalyticsProvider::class
        ),
    ],
    routePrefix: '/notification-tracker'
)]
class DetailedAnalyticsDto
{
    public function __construct(
        public readonly string $period = '30d',
        public readonly string $groupBy = 'day',
        public readonly ?string $channel = null,
        public readonly array $data = [],
        public readonly array $summary = [],
        public readonly array $breakdowns = [],
        public readonly array $links = []
    ) {
    }
}
