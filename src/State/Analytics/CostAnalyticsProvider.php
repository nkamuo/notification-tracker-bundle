<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\State\Analytics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Nkamuo\NotificationTrackerBundle\DTO\Analytics\CostAnalyticsDto;
use Nkamuo\NotificationTrackerBundle\Service\Analytics\AnalyticsService;
use Symfony\Component\HttpFoundation\RequestStack;

class CostAnalyticsProvider implements ProviderInterface
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CostAnalyticsDto
    {
        $request = $this->requestStack->getCurrentRequest();
        $period = $request?->query->get('period', '30d');
        $currency = $request?->query->get('currency', 'USD');

        try {
            // Get cost analytics data from the analytics service
            $costs = $this->analyticsService->getCostAnalytics($period, $currency);
            
            // Generate efficiency metrics based on cost data
            $efficiency = $this->generateEfficiencyMetrics($costs, $period);
            
            // Generate optimization suggestions
            $optimization = $this->generateOptimizationSuggestions($costs, $period);
            
            // Generate cost forecasts
            $forecasts = $this->generateCostForecasts($costs, $period, $currency);

            return new CostAnalyticsDto(
                period: $period,
                currency: $currency,
                costs: $costs,
                efficiency: $efficiency,
                optimization: $optimization,
                forecasts: $forecasts
            );
        } catch (\Exception $e) {
            // Return empty analytics data in case of error
            return new CostAnalyticsDto(
                period: $period,
                currency: $currency,
                costs: [],
                efficiency: [],
                optimization: [],
                forecasts: []
            );
        }
    }

    private function generateEfficiencyMetrics(array $costs, string $period): array
    {
        if (empty($costs)) {
            return [];
        }

        // Calculate efficiency metrics based on cost data
        $totalCost = array_sum(array_column($costs, 'cost')) ?: 1;
        $totalMessages = array_sum(array_column($costs, 'count')) ?: 1;
        $avgCostPerMessage = $totalCost / $totalMessages;

        return [
            'cost_per_message' => round($avgCostPerMessage, 4),
            'efficiency_score' => min(100, round((1 / $avgCostPerMessage) * 100, 2)),
            'period' => $period,
            'total_cost' => $totalCost,
            'total_messages' => $totalMessages
        ];
    }

    private function generateOptimizationSuggestions(array $costs, string $period): array
    {
        if (empty($costs)) {
            return [];
        }

        $suggestions = [];
        
        // Find channels with high costs
        foreach ($costs as $channelData) {
            if (isset($channelData['cost']) && $channelData['cost'] > 100) {
                $suggestions[] = [
                    'type' => 'high_cost_channel',
                    'channel' => $channelData['channel'] ?? 'unknown',
                    'message' => 'Consider optimizing this high-cost channel',
                    'potential_savings' => round($channelData['cost'] * 0.1, 2)
                ];
            }
        }

        return $suggestions;
    }

    private function generateCostForecasts(array $costs, string $period, string $currency): array
    {
        if (empty($costs)) {
            return [];
        }

        $totalCost = array_sum(array_column($costs, 'cost'));
        
        // Simple forecast based on current trends
        return [
            'next_period' => [
                'period' => $this->getNextPeriod($period),
                'estimated_cost' => round($totalCost * 1.05, 2), // 5% increase
                'currency' => $currency
            ],
            'confidence' => 75,
            'trend' => 'increasing'
        ];
    }

    private function getNextPeriod(string $period): string
    {
        // Simple period increment logic
        if ($period === '7d') return '14d';
        if ($period === '30d') return '60d';
        if ($period === '90d') return '180d';
        return $period;
    }
}
