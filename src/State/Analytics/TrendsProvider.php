<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\State\Analytics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Nkamuo\NotificationTrackerBundle\Dto\Analytics\TrendsDto;
use Nkamuo\NotificationTrackerBundle\Service\Analytics\AnalyticsService;
use Symfony\Component\HttpFoundation\RequestStack;

class TrendsProvider implements ProviderInterface
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TrendsDto
    {
        $request = $this->requestStack->getCurrentRequest();
        $period = $request?->query->get('period', '30d');
        $granularity = $request?->query->get('granularity', 'day');
        $metricsParam = $request?->query->get('metrics', 'volume,delivery,engagement');
        $metrics = is_string($metricsParam) ? explode(',', $metricsParam) : ['volume', 'delivery', 'engagement'];

        try {
            // Since there's no specific trends method, we'll get dashboard analytics and extract trends
            $dashboardData = $this->analyticsService->getDashboardAnalytics($period, 'UTC');
            $trends = $dashboardData['trends'] ?? [];

            // Generate chart data and insights based on trends
            $chartData = $this->generateChartData($trends, $metrics, $granularity);
            $insights = $this->generateInsights($trends, $period);
            $forecasts = $this->generateForecasts($trends, $period);

            return new TrendsDto(
                period: $period,
                granularity: $granularity,
                metrics: $metrics,
                chartData: $chartData,
                insights: $insights,
                forecasts: $forecasts
            );
        } catch (\Exception $e) {
            // Return empty analytics data in case of error
            return new TrendsDto(
                period: $period,
                granularity: $granularity,
                metrics: $metrics,
                chartData: [],
                insights: [],
                forecasts: []
            );
        }
    }

    private function generateChartData(array $trends, array $metrics, string $granularity): array
    {
        $chartData = [];
        
        foreach ($metrics as $metric) {
            $chartData[$metric] = [
                'label' => ucfirst($metric),
                'data' => $trends[$metric] ?? [],
                'granularity' => $granularity
            ];
        }

        return $chartData;
    }

    private function generateInsights(array $trends, string $period): array
    {
        $insights = [];

        // Generate insights based on trend data
        foreach ($trends as $metric => $data) {
            if (is_array($data) && count($data) > 1) {
                $latest = end($data);
                $previous = prev($data);
                
                if ($latest && $previous && isset($latest['value']) && isset($previous['value'])) {
                    $change = $latest['value'] - $previous['value'];
                    $percentChange = $previous['value'] > 0 ? ($change / $previous['value']) * 100 : 0;
                    
                    $insights[] = [
                        'metric' => $metric,
                        'trend' => $change > 0 ? 'increasing' : ($change < 0 ? 'decreasing' : 'stable'),
                        'change' => $change,
                        'percentChange' => round($percentChange, 2),
                        'message' => $this->generateInsightMessage($metric, $percentChange)
                    ];
                }
            }
        }

        return $insights;
    }

    private function generateForecasts(array $trends, string $period): array
    {
        $forecasts = [];

        foreach ($trends as $metric => $data) {
            if (is_array($data) && count($data) >= 3) {
                $values = array_column($data, 'value');
                $avgChange = (end($values) - $values[0]) / count($values);
                $nextValue = end($values) + $avgChange;

                $forecasts[] = [
                    'metric' => $metric,
                    'nextPeriod' => $this->getNextPeriod($period),
                    'estimatedValue' => round($nextValue, 2),
                    'confidence' => min(100, max(50, 100 - (abs($avgChange) * 10)))
                ];
            }
        }

        return $forecasts;
    }

    private function generateInsightMessage(string $metric, float $percentChange): string
    {
        $absChange = abs($percentChange);
        
        if ($absChange < 5) {
            return "{$metric} remains stable with minimal change";
        } elseif ($percentChange > 0) {
            return "{$metric} is trending upward by {$absChange}%";
        } else {
            return "{$metric} is declining by {$absChange}%";
        }
    }

    private function getNextPeriod(string $period): string
    {
        // Simple period increment logic
        return match ($period) {
            '7d' => '14d',
            '30d' => '60d',
            '90d' => '180d',
            default => $period
        };
    }
}
