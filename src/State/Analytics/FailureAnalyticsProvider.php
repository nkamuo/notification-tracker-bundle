<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\State\Analytics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Nkamuo\NotificationTrackerBundle\Dto\Analytics\FailureAnalyticsDto;
use Nkamuo\NotificationTrackerBundle\Service\Analytics\AnalyticsService;
use Symfony\Component\HttpFoundation\RequestStack;

class FailureAnalyticsProvider implements ProviderInterface
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FailureAnalyticsDto
    {
        $request = $this->requestStack->getCurrentRequest();
        $period = $request?->query->get('period', '30d');
        $channel = $request?->query->get('channel');
        $groupBy = $request?->query->get('groupBy', 'reason');

        try {
            // Get failure analytics data from the analytics service
            $data = $this->analyticsService->getFailureAnalytics($period, $channel, $groupBy);

            return new FailureAnalyticsDto(
                period: $period,
                channel: $channel,
                groupBy: $groupBy,
                failures: $data['failures'] ?? [],
                patterns: $data['patterns'] ?? [],
                recommendations: $data['recommendations'] ?? [],
                trends: $data['trends'] ?? []
            );
        } catch (\Exception $e) {
            // Return empty analytics data in case of error
            return new FailureAnalyticsDto(
                period: $period,
                channel: $channel,
                groupBy: $groupBy,
                failures: [],
                patterns: [],
                recommendations: [],
                trends: []
            );
        }
    }
}
