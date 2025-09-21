<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\DTO\Analytics;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Nkamuo\NotificationTrackerBundle\State\Analytics\ChannelAnalyticsProvider;

#[ApiResource(
    shortName: 'ChannelAnalytics',
    operations: [
        new Get(
            uriTemplate: '/analytics/channels',
            provider: ChannelAnalyticsProvider::class
        ),
    ],
    routePrefix: '/notification-tracker'
)]
class ChannelAnalyticsDto
{
    public function __construct(
        public readonly string $period = '30d',
        public readonly bool $compare = false,
        public readonly array $channels = [],
        public readonly array $comparison = [],
        public readonly array $recommendations = [],
        public readonly array $links = []
    ) {
    }
}
