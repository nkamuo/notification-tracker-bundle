<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\State\Analytics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Nkamuo\NotificationTrackerBundle\Dto\Analytics\ChannelAnalyticsDto;
use Nkamuo\NotificationTrackerBundle\Service\Analytics\AnalyticsService;
use Symfony\Component\HttpFoundation\RequestStack;

class ChannelAnalyticsProvider implements ProviderInterface
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ChannelAnalyticsDto
    {
        $request = $this->requestStack->getCurrentRequest();
        $period = $request?->query->get('period', '30d');
        $compare = $request?->query->getBoolean('compare', false);

        $data = $this->analyticsService->getChannelAnalytics($period, $compare);
        
        $links = [
            'dashboard' => "/notification-tracker/analytics/dashboard?period={$period}",
            'detailed' => "/notification-tracker/analytics/detailed?period={$period}",
        ];

        return new ChannelAnalyticsDto(
            period: $period,
            compare: $compare,
            channels: $data['channels'] ?? [],
            comparison: $data['comparison'] ?? [],
            recommendations: $data['recommendations'] ?? [],
            links: $links
        );
    }
}
