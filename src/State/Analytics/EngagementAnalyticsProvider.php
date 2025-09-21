<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\State\Analytics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Nkamuo\NotificationTrackerBundle\DTO\Analytics\EngagementAnalyticsDto;
use Nkamuo\NotificationTrackerBundle\Service\Analytics\AnalyticsService;
use Symfony\Component\HttpFoundation\RequestStack;

class EngagementAnalyticsProvider implements ProviderInterface
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): EngagementAnalyticsDto
    {
        $request = $this->requestStack->getCurrentRequest();
        $period = $request?->query->get('period', '30d');
        $segment = $request?->query->get('segment');

        try {
            // Get engagement analytics data from the analytics service
            $data = $this->analyticsService->getEngagementAnalytics($period, $segment);

            return new EngagementAnalyticsDto(
                period: $period,
                segment: $segment,
                metrics: $data['metrics'] ?? [],
                cohortAnalysis: $data['cohortAnalysis'] ?? [],
                funnelData: $data['funnelData'] ?? [],
                heatmaps: $data['heatmaps'] ?? []
            );
        } catch (\Exception $e) {
            // Return empty analytics data in case of error
            return new EngagementAnalyticsDto(
                period: $period,
                segment: $segment,
                metrics: [],
                cohortAnalysis: [],
                funnelData: [],
                heatmaps: []
            );
        }
    }
}
