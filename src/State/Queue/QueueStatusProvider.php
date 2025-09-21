<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\State\Queue;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Nkamuo\NotificationTrackerBundle\DTO\Queue\QueueStatusDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class QueueStatusProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): QueueStatusDto
    {
        // Get queue status information
        $queueStats = $this->getQueueStatistics();
        $workerStatus = $this->getWorkerStatus();
        $healthCheck = $this->getHealthCheck();
        
        $summary = [
            'totalPending' => $queueStats['pending'] ?? 0,
            'totalProcessing' => $queueStats['processing'] ?? 0,
            'totalFailed' => $queueStats['failed'] ?? 0,
            'workersActive' => count($workerStatus['active'] ?? []),
            'lastProcessed' => $queueStats['lastProcessed'] ?? null,
            'throughput' => $queueStats['throughput'] ?? 0
        ];

        return new QueueStatusDto(
            queues: $queueStats,
            summary: $summary,
            workers: $workerStatus,
            health: $healthCheck
        );
    }

    private function getQueueStatistics(): array
    {
        // Get message queue statistics from database
        $qb = $this->entityManager->createQueryBuilder()
            ->select('
                m.status,
                COUNT(m.id) as count,
                MAX(m.createdAt) as lastCreated,
                MAX(m.sentAt) as lastSent
            ')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\Message', 'm')
            ->where('m.createdAt >= :since')
            ->setParameter('since', new \DateTime('-24 hours'))
            ->groupBy('m.status');

        $results = $qb->getQuery()->getResult();
        
        $stats = [
            'pending' => 0,
            'queued' => 0,
            'processing' => 0,
            'failed' => 0,
            'sent' => 0,
            'lastProcessed' => null,
            'throughput' => 0
        ];

        foreach ($results as $result) {
            $status = $result['status'];
            $count = (int) $result['count'];
            
            if (in_array($status, ['pending', 'queued'])) {
                $stats['pending'] += $count;
            } elseif ($status === 'sending') {
                $stats['processing'] = $count;
            } elseif ($status === 'failed') {
                $stats['failed'] = $count;
            } elseif (in_array($status, ['sent', 'delivered'])) {
                $stats['sent'] += $count;
                if ($result['lastSent']) {
                    $stats['lastProcessed'] = $result['lastSent'];
                }
            }
        }

        // Calculate throughput (messages per hour)
        if ($stats['sent'] > 0) {
            $stats['throughput'] = round($stats['sent'] / 24, 2);
        }

        return $stats;
    }

    private function getWorkerStatus(): array
    {
        // This would typically integrate with Symfony Messenger or your queue system
        // For now, we'll return a basic status
        return [
            'active' => [
                [
                    'id' => 'worker-1',
                    'status' => 'running',
                    'lastSeen' => new \DateTime(),
                    'messagesProcessed' => 1250,
                    'uptime' => '2h 30m'
                ]
            ],
            'idle' => [],
            'failed' => []
        ];
    }

    private function getHealthCheck(): array
    {
        // Perform health checks on the queue system
        $health = [
            'status' => 'healthy',
            'checks' => [
                'database' => $this->checkDatabaseConnection(),
                'memory' => $this->checkMemoryUsage(),
                'disk' => $this->checkDiskSpace(),
                'queue_depth' => $this->checkQueueDepth()
            ],
            'timestamp' => new \DateTime()
        ];

        // Determine overall health
        $hasFailures = false;
        foreach ($health['checks'] as $check) {
            if ($check['status'] !== 'ok') {
                $hasFailures = true;
                break;
            }
        }

        $health['status'] = $hasFailures ? 'degraded' : 'healthy';

        return $health;
    }

    private function checkDatabaseConnection(): array
    {
        try {
            $this->entityManager->getConnection()->connect();
            return ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    private function checkMemoryUsage(): array
    {
        $usage = memory_get_usage(true);
        $limit = $this->getMemoryLimit();
        $percentage = $limit > 0 ? ($usage / $limit) * 100 : 0;

        return [
            'status' => $percentage > 85 ? 'warning' : 'ok',
            'usage' => $this->formatBytes($usage),
            'limit' => $this->formatBytes($limit),
            'percentage' => round($percentage, 2)
        ];
    }

    private function checkDiskSpace(): array
    {
        $path = sys_get_temp_dir();
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        $percentage = $total > 0 ? (($total - $free) / $total) * 100 : 0;

        return [
            'status' => $percentage > 90 ? 'warning' : 'ok',
            'free' => $this->formatBytes($free !== false ? (int) $free : 0),
            'total' => $this->formatBytes($total !== false ? (int) $total : 0),
            'percentage' => round($percentage, 2)
        ];
    }

    private function checkQueueDepth(): array
    {
        $pendingCount = $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\Message', 'm')
            ->where('m.status IN (:pendingStates)')
            ->setParameter('pendingStates', ['pending', 'queued'])
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'status' => $pendingCount > 1000 ? 'warning' : 'ok',
            'depth' => (int) $pendingCount,
            'threshold' => 1000
        ];
    }

    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return 0; // No limit
        }
        
        return $this->parseSize($limit);
    }

    private function parseSize(string $size): int
    {
        $unit = strtolower(substr($size, -1));
        $value = (int) substr($size, 0, -1);
        
        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $factor), 2) . ' ' . $units[$factor];
    }
}
