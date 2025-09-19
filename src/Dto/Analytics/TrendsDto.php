<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Dto\Analytics;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Nkamuo\NotificationTrackerBundle\State\Analytics\TrendsProvider;

#[ApiResource(
    shortName: 'AnalyticsTrends',
    operations: [
        new Get(
            uriTemplate: '/analytics/trends',
            provider: TrendsProvider::class
        ),
    ],
    routePrefix: '/notification-tracker'
)]
class TrendsDto
{
    public function __construct(
        public readonly string $period = '30d',
        public readonly string $granularity = 'day',
        public readonly array $metrics = ['volume', 'delivery', 'engagement'],
        public readonly array $chartData = [],
        public readonly array $insights = [],
        public readonly array $forecasts = []
    ) {
    }
}
