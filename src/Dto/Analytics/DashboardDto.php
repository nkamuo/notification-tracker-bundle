<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Dto\Analytics;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Nkamuo\NotificationTrackerBundle\State\Analytics\DashboardProvider;

#[ApiResource(
    shortName: 'AnalyticsDashboard',
    operations: [
        new Get(
            uriTemplate: '/analytics/dashboard',
            provider: DashboardProvider::class
        ),
    ],
    routePrefix: '/notification-tracker'
)]
class DashboardDto
{
    public function __construct(
        public readonly string $period = '30d',
        public readonly string $timezone = 'UTC',
        public readonly array $summary = [],
        public readonly array $channels = [],
        public readonly array $trends = [],
        public readonly array $topPerforming = [],
        public readonly array $links = []
    ) {
    }
}
