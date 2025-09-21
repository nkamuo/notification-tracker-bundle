<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\State\Analytics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Nkamuo\NotificationTrackerBundle\Dto\Analytics\SystemLogsDto;
use Nkamuo\NotificationTrackerBundle\Service\Analytics\AnalyticsService;
use Symfony\Component\HttpFoundation\RequestStack;

class SystemLogsProvider implements ProviderInterface
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = (int) ($request?->query->get('page', 1));
        $limit = (int) ($request?->query->get('limit', 50));
        $level = $request?->query->get('level');
        $channel = $request?->query->get('channel');
        $period = $request?->query->get('period', '7d');

        try {
            // Get system logs data from the analytics service
            $data = $this->analyticsService->getSystemLogs($page, $limit, $level, $channel, $period);
            $logs = $data['logs'] ?? [];

            $systemLogsDtos = [];
            foreach ($logs as $log) {
                $systemLogsDtos[] = new SystemLogsDto(
                    id: (string) ($log['id'] ?? uniqid()),
                    timestamp: $log['occurredAt']?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
                    level: $this->mapEventTypeToLevel($log['eventType'] ?? 'info'),
                    message: $this->formatLogMessage($log),
                    channel: $log['channel'] ?? null,
                    context: $log['eventData'] ?? [],
                    metadata: [
                        'pagination' => $data['pagination'] ?? []
                    ]
                );
            }

            return $systemLogsDtos;
        } catch (\Exception $e) {
            // Return empty array in case of error
            return [];
        }
    }

    private function mapEventTypeToLevel(string $eventType): string
    {
        return match ($eventType) {
            'failed' => 'error',
            'retry' => 'warning',
            'delivered', 'sent' => 'info',
            'queued' => 'debug',
            default => 'info'
        };
    }

    private function formatLogMessage(array $log): string
    {
        $eventType = $log['eventType'] ?? 'unknown';
        $channel = $log['channel'] ?? 'unknown';
        
        return match ($eventType) {
            'sent' => "Message sent successfully via {$channel}",
            'delivered' => "Message delivered via {$channel}",
            'failed' => "Message failed to send via {$channel}",
            'retry' => "Message retry attempt via {$channel}",
            'queued' => "Message queued for {$channel}",
            default => "Event '{$eventType}' occurred for {$channel}"
        };
    }
}
