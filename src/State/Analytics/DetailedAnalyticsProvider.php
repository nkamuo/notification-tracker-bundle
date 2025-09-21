<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\State\Analytics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Nkamuo\NotificationTrackerBundle\DTO\Analytics\DetailedAnalyticsDto;
use Nkamuo\NotificationTrackerBundle\Service\Analytics\AnalyticsService;
use Symfony\Component\HttpFoundation\RequestStack;

class DetailedAnalyticsProvider implements ProviderInterface
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DetailedAnalyticsDto
    {
        $request = $this->requestStack->getCurrentRequest();
        $period = $request?->query->get('period', '30d');
        $groupBy = $request?->query->get('groupBy', 'day');
        $channel = $request?->query->get('channel');

        $data = $this->analyticsService->getDetailedAnalytics($period, $groupBy, $channel);
        
        $links = [
            'dashboard' => "/notification-tracker/analytics/dashboard?period={$period}",
            'channels' => "/notification-tracker/analytics/channels?period={$period}",
        ];

        return new DetailedAnalyticsDto(
            period: $period,
            groupBy: $groupBy,
            channel: $channel,
            data: $data['data'] ?? [],
            summary: $data['summary'] ?? [],
            breakdowns: $data['breakdowns'] ?? [],
            links: $links
        );
    }
}
