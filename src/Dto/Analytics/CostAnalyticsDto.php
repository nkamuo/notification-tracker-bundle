<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Dto\Analytics;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\NotificationTrackerBundle\State\Analytics\CostAnalyticsProvider;

#[ApiResource(
    shortName: 'CostAnalytics',
    operations: [
        new Get(
            uriTemplate: '/analytics/costs',
            provider: CostAnalyticsProvider::class
        ),
    ],
    routePrefix: '/notification-tracker'
)]
class CostAnalyticsDto
{
    public function __construct(
        public readonly string $period = '30d',
        public readonly string $currency = 'USD',
        public readonly array $costs = [],
        public readonly array $efficiency = [],
        public readonly array $optimization = [],
        public readonly array $forecasts = []
    ) {
    }
}
