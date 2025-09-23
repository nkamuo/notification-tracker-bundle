<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\State\Analytics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Nkamuo\NotificationTrackerBundle\DTO\Analytics\RealtimeAnalyticsDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class RealtimeProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): RealtimeAnalyticsDto
    {
        $liveMetrics = $this->getLiveMetrics();
        $recentActivity = $this->getRecentActivity();
        $alerts = $this->getActiveAlerts();
        $performance = $this->getPerformanceMetrics();
        
        return new RealtimeAnalyticsDto(
            liveMetrics: $liveMetrics,
            recentActivity: $recentActivity,
            alerts: $alerts,
            performance: $performance,
            timestamp: (new \DateTime())->format('c'),
            refreshInterval: 30
        );
    }

    private function getLiveMetrics(): array
    {
        // Get metrics for the last hour
        $since = new \DateTime('-1 hour');
        
        // Messages in the last hour
        $hourlyStats = $this->entityManager->createQueryBuilder()
            ->select('
                COUNT(m.id) as total,
                SUM(CASE WHEN m.status = :sent THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN m.status = :delivered THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN m.status = :failed THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN m.status IN (:pending) THEN 1 ELSE 0 END) as pending
            ')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\Message', 'm')
            ->where('m.createdAt >= :since')
            ->setParameter('since', $since)
            ->setParameter('sent', 'sent')
            ->setParameter('delivered', 'delivered')
            ->setParameter('failed', 'failed')
            ->setParameter('pending', ['pending', 'queued'])
            ->getQuery()
            ->getOneOrNullResult();

        // Current queue depth
        $queueDepth = $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\Message', 'm')
            ->where('m.status IN (:pendingStates)')
            ->setParameter('pendingStates', ['pending', 'queued'])
            ->getQuery()
            ->getSingleScalarResult();

        // Messages per minute (last 5 minutes)
        $recentRate = $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\Message', 'm')
            ->where('m.createdAt >= :since')
            ->setParameter('since', new \DateTime('-5 minutes'))
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'messagesLastHour' => (int) ($hourlyStats['total'] ?? 0),
            'deliveredLastHour' => (int) ($hourlyStats['delivered'] ?? 0),
            'failedLastHour' => (int) ($hourlyStats['failed'] ?? 0),
            'pendingMessages' => (int) $queueDepth,
            'messagesPerMinute' => round($recentRate / 5, 2),
            'deliveryRate' => $hourlyStats['total'] > 0 ? 
                round(($hourlyStats['delivered'] / $hourlyStats['total']) * 100, 2) : 0,
            'failureRate' => $hourlyStats['total'] > 0 ? 
                round(($hourlyStats['failed'] / $hourlyStats['total']) * 100, 2) : 0
        ];
    }

    private function getRecentActivity(): array
    {
        // Get recent message events - simplified approach without discriminator column
        $recentEvents = $this->entityManager->createQueryBuilder()
            ->select('e.id, e.eventType, e.occurredAt, n.type as notificationType, m.id as messageId')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\MessageEvent', 'e')
            ->join('e.message', 'm')
            ->join('m.notification', 'n')
            ->where('e.occurredAt >= :since')
            ->setParameter('since', new \DateTime('-10 minutes'))
            ->orderBy('e.occurredAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        // Format for display with channel determination
        $activity = [];
        foreach ($recentEvents as $event) {
            // Get the message to determine its type
            $message = $this->entityManager->find('Nkamuo\NotificationTrackerBundle\Entity\Message', $event['messageId']);
            $channel = 'unknown';
            
            if ($message) {
                $channel = match (true) {
                    $message instanceof \Nkamuo\NotificationTrackerBundle\Entity\EmailMessage => 'email',
                    $message instanceof \Nkamuo\NotificationTrackerBundle\Entity\SmsMessage => 'sms',
                    $message instanceof \Nkamuo\NotificationTrackerBundle\Entity\PushMessage => 'push',
                    $message instanceof \Nkamuo\NotificationTrackerBundle\Entity\SlackMessage => 'slack',
                    $message instanceof \Nkamuo\NotificationTrackerBundle\Entity\TelegramMessage => 'telegram',
                    default => 'unknown'
                };
            }
            
            $activity[] = [
                'type' => $event['eventType'],
                'channel' => $channel,
                'notificationType' => $event['notificationType'],
                'timestamp' => $event['occurredAt']->format('c'),
                'timeAgo' => $this->timeAgo($event['occurredAt'])
            ];
        }

        return $activity;
    }

    private function getActiveAlerts(): array
    {
        $alerts = [];
        
        // Check for high failure rate
        $recentFailures = $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id) as failedCount')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\Message', 'm')
            ->where('m.status = :failed')
            ->andWhere('m.createdAt >= :since')
            ->setParameter('failed', 'failed')
            ->setParameter('since', new \DateTime('-1 hour'))
            ->getQuery()
            ->getSingleScalarResult();

        $totalRecent = $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\Message', 'm')
            ->where('m.createdAt >= :since')
            ->setParameter('since', new \DateTime('-1 hour'))
            ->getQuery()
            ->getSingleScalarResult();

        if ($totalRecent > 0) {
            $failureRate = ($recentFailures / $totalRecent) * 100;
            if ($failureRate > 10) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'High Failure Rate',
                    'message' => "Failure rate is {$failureRate}% in the last hour",
                    'severity' => $failureRate > 25 ? 'critical' : 'warning',
                    'timestamp' => (new \DateTime())->format('c')
                ];
            }
        }

        // Check for queue backup
        $queueDepth = $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\Message', 'm')
            ->where('m.status IN (:pendingStates)')
            ->setParameter('pendingStates', ['pending', 'queued'])
            ->getQuery()
            ->getSingleScalarResult();

        if ($queueDepth > 1000) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Queue Backup',
                'message' => "{$queueDepth} messages pending in queue",
                'severity' => $queueDepth > 5000 ? 'critical' : 'warning',
                'timestamp' => (new \DateTime())->format('c')
            ];
        }

        return $alerts;
    }

    private function getPerformanceMetrics(): array
    {
        // Average processing time for the last hour
        $avgProcessingTime = $this->entityManager->createQueryBuilder()
            ->select('AVG(CASE WHEN m.sentAt IS NOT NULL AND m.createdAt IS NOT NULL THEN 
                EXTRACT(EPOCH FROM (m.sentAt - m.createdAt)) ELSE NULL END)')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\Message', 'm')
            ->where('m.sentAt >= :since')
            ->andWhere('m.sentAt IS NOT NULL')
            ->setParameter('since', new \DateTime('-1 hour'))
            ->getQuery()
            ->getSingleScalarResult();

        // Channel performance breakdown for last hour - using fallback method
        $channelTypes = [
            'email' => 'Nkamuo\NotificationTrackerBundle\Entity\EmailMessage',
            'sms' => 'Nkamuo\NotificationTrackerBundle\Entity\SmsMessage',
            'push' => 'Nkamuo\NotificationTrackerBundle\Entity\PushMessage',
            'slack' => 'Nkamuo\NotificationTrackerBundle\Entity\SlackMessage',
            'telegram' => 'Nkamuo\NotificationTrackerBundle\Entity\TelegramMessage'
        ];

        $channelPerformance = [];
        foreach ($channelTypes as $channelName => $channelClass) {
            $performance = $this->entityManager->createQueryBuilder()
                ->select('
                    COUNT(m.id) as total,
                    SUM(CASE WHEN m.status = :delivered THEN 1 ELSE 0 END) as delivered,
                    AVG(CASE WHEN m.sentAt IS NOT NULL AND m.createdAt IS NOT NULL THEN 
                        EXTRACT(EPOCH FROM (m.sentAt - m.createdAt)) ELSE NULL END) as avgTime
                ')
                ->from($channelClass, 'm')
                ->where('m.createdAt >= :since')
                ->setParameter('since', new \DateTime('-1 hour'))
                ->setParameter('delivered', 'delivered')
                ->getQuery()
                ->getOneOrNullResult();

            if ($performance && $performance['total'] > 0) {
                $channelPerformance[] = array_merge($performance, ['channel' => $channelName]);
            }
        }

        $channels = [];
        foreach ($channelPerformance as $channel) {
            $total = (int) $channel['total'];
            $delivered = (int) $channel['delivered'];
            
            $channels[$channel['channel']] = [
                'total' => $total,
                'delivered' => $delivered,
                'deliveryRate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
                'avgProcessingTime' => $channel['avgTime'] ? round($channel['avgTime'], 2) : null
            ];
        }

        return [
            'avgProcessingTime' => $avgProcessingTime ? round($avgProcessingTime, 2) : null,
            'channels' => $channels,
            'systemLoad' => [
                'cpu' => $this->getCpuUsage(),
                'memory' => $this->getMemoryUsage(),
                'connections' => $this->getActiveConnections()
            ]
        ];
    }

    private function timeAgo(\DateTime $datetime): string
    {
        $now = new \DateTime();
        $diff = $now->diff($datetime);
        
        if ($diff->i < 1) {
            return 'just now';
        } elseif ($diff->i < 60) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h < 24) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } else {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        }
    }

    private function getCpuUsage(): ?float
    {
        // This is a simplified CPU usage check
        // In production, you might want to use a more sophisticated method
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0] ?? null;
        }
        return null;
    }

    private function getMemoryUsage(): array
    {
        return [
            'used' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => $this->getMemoryLimit()
        ];
    }

    private function getActiveConnections(): int
    {
        // This would typically query your database connection pool
        // For now, return a placeholder
        return 1;
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
}
