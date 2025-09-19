<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\State\Analytics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Nkamuo\NotificationTrackerBundle\Dto\Analytics\DashboardDto;
use Nkamuo\NotificationTrackerBundle\Service\Analytics\AnalyticsService;
use Symfony\Component\HttpFoundation\RequestStack;

class DashboardProvider implements ProviderInterface
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DashboardDto
    {
        $request = $this->requestStack->getCurrentRequest();
        $period = $request?->query->get('period', '30d');
        $timezone = $request?->query->get('timezone', 'UTC');

        $data = $this->analyticsService->getDashboardAnalytics($period, $timezone);
        
        // Add navigation links for deeper analysis
        $links = [
            'detailed' => "/notification-tracker/analytics/detailed?period={$period}",
            'channels' => "/notification-tracker/analytics/channels?period={$period}",
            'trends' => "/notification-tracker/analytics/trends?period={$period}",
            'failures' => "/notification-tracker/analytics/failures?period={$period}",
            'engagement' => "/notification-tracker/analytics/engagement?period={$period}",
            'costs' => "/notification-tracker/analytics/costs?period={$period}"
        ];

        return new DashboardDto(
            period: $period,
            timezone: $timezone,
            summary: $data['summary'] ?? [],
            channels: $data['channels'] ?? [],
            trends: $data['trends'] ?? [],
            topPerforming: $data['topPerforming'] ?? [],
            links: $links
        );
    }
}
