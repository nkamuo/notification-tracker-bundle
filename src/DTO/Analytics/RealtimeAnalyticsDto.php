<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\DTO\Analytics;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Nkamuo\NotificationTrackerBundle\State\Analytics\RealtimeProvider;

#[ApiResource(
    shortName: 'RealtimeAnalytics',
    operations: [
        new Get(
            uriTemplate: '/analytics/realtime',
            provider: RealtimeProvider::class
        ),
    ],
    routePrefix: '/notification-tracker'
)]
class RealtimeAnalyticsDto
{
    public function __construct(
        public readonly array $liveMetrics = [],
        public readonly array $recentActivity = [],
        public readonly array $alerts = [],
        public readonly array $performance = [],
        public readonly string $timestamp = '',
        public readonly int $refreshInterval = 30
    ) {
    }
}
