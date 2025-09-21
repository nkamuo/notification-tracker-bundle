<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Dto\Analytics;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Nkamuo\NotificationTrackerBundle\State\Analytics\EngagementAnalyticsProvider;

#[ApiResource(
    shortName: 'EngagementAnalytics',
    operations: [
        new Get(
            uriTemplate: '/analytics/engagement',
            provider: EngagementAnalyticsProvider::class
        ),
    ],
    routePrefix: '/notification-tracker'
)]
class EngagementAnalyticsDto
{
    public function __construct(
        public readonly string $period = '30d',
        public readonly ?string $segment = null,
        public readonly array $metrics = [],
        public readonly array $cohortAnalysis = [],
        public readonly array $funnelData = [],
        public readonly array $heatmaps = []
    ) {
    }
}
