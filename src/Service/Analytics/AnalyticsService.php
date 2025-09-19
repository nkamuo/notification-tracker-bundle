<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Service\Analytics;

use Nkamuo\NotificationTrackerBundle\Repository\MessageRepository;
use Nkamuo\NotificationTrackerBundle\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

class AnalyticsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notificationRepository,
        private readonly MessageRepository $messageRepository
    ) {
    }

    public function getDashboardAnalytics(string $period, string $timezone): array
    {
        $dateRange = $this->parsePeriod($period, $timezone);
        
        return [
            'summary' => $this->getSummaryMetrics($dateRange),
            'channels' => $this->getChannelMetrics($dateRange),
            'trends' => $this->getTrendData($dateRange),
            'topPerforming' => $this->getTopPerformingTypes($dateRange),
        ];
    }

    public function getDetailedAnalytics(string $period, string $groupBy, ?string $channel): array
    {
        $dateRange = $this->parsePeriod($period);
        
        $qb = $this->entityManager->createQueryBuilder()
            ->select('n.type, n.importance, COUNT(n.id) as notificationCount, COUNT(m.id) as messageCount')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\Notification', 'n')
            ->leftJoin('n.messages', 'm')
            ->where('n.createdAt >= :startDate')
            ->andWhere('n.createdAt <= :endDate')
            ->setParameter('startDate', $dateRange['start'])
            ->setParameter('endDate', $dateRange['end'])
            ->groupBy('n.type, n.importance');

        if ($channel) {
            $qb->andWhere('m.type = :channel')
               ->setParameter('channel', $channel);
        }

        $results = $qb->getQuery()->getResult();
        
        return [
            'period' => $period,
            'groupBy' => $groupBy,
            'channel' => $channel,
            'data' => $this->formatDetailedResults($results, $groupBy),
            'summary' => $this->calculateDetailedSummary($results),
            'breakdowns' => $this->getBreakdownAnalysis($dateRange, $channel)
        ];
    }

    public function getChannelAnalytics(string $period, bool $compare): array
    {
        $dateRange = $this->parsePeriod($period);
        $channels = ['email', 'sms', 'push', 'slack', 'telegram'];
        
        $channelData = [];
        $comparisonData = [];
        
        foreach ($channels as $channel) {
            $channelData[$channel] = $this->getChannelMetrics($dateRange, $channel);
            
            if ($compare) {
                $previousRange = $this->getPreviousPeriod($dateRange);
                $comparisonData[$channel] = $this->getChannelMetrics($previousRange, $channel);
            }
        }
        
        return [
            'period' => $period,
            'channels' => $channelData,
            'comparison' => $comparisonData,
            'recommendations' => $this->generateChannelRecommendations($channelData)
        ];
    }

    public function getChannelDetailAnalytics(string $channel, string $period): array
    {
        $dateRange = $this->parsePeriod($period);
        
        // Detailed message status breakdown for specific channel
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m.status, COUNT(m.id) as count, 
                     AVG(CASE WHEN m.sentAt IS NOT NULL THEN 
                         EXTRACT(EPOCH FROM (m.sentAt - m.createdAt)) 
                     ELSE NULL END) as avgDeliveryTime')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\Message', 'm')
            ->where('m.type = :channel')
            ->andWhere('m.createdAt >= :startDate')
            ->andWhere('m.createdAt <= :endDate')
            ->setParameter('channel', $channel)
            ->setParameter('startDate', $dateRange['start'])
            ->setParameter('endDate', $dateRange['end'])
            ->groupBy('m.status');

        $statusBreakdown = $qb->getQuery()->getResult();
        
        // Transport performance for this channel
        $transportQb = $this->entityManager->createQueryBuilder()
            ->select('m.transportName, COUNT(m.id) as total,
                     SUM(CASE WHEN m.status = :sent THEN 1 ELSE 0 END) as sent,
                     SUM(CASE WHEN m.status = :delivered THEN 1 ELSE 0 END) as delivered,
                     SUM(CASE WHEN m.status = :failed THEN 1 ELSE 0 END) as failed')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\Message', 'm')
            ->where('m.type = :channel')
            ->andWhere('m.createdAt >= :startDate')
            ->andWhere('m.createdAt <= :endDate')
            ->setParameter('channel', $channel)
            ->setParameter('startDate', $dateRange['start'])
            ->setParameter('endDate', $dateRange['end'])
            ->setParameter('sent', 'sent')
            ->setParameter('delivered', 'delivered')
            ->setParameter('failed', 'failed')
            ->groupBy('m.transportName');

        $transportPerformance = $transportQb->getQuery()->getResult();
        
        return [
            'channel' => $channel,
            'period' => $period,
            'statusBreakdown' => $statusBreakdown,
            'transportPerformance' => $transportPerformance,
            'engagementMetrics' => $this->getChannelEngagementMetrics($channel, $dateRange),
            'timeSeriesData' => $this->getChannelTimeSeries($channel, $dateRange),
            'recommendations' => $this->generateChannelSpecificRecommendations($channel, $statusBreakdown)
        ];
    }

    public function getEngagementAnalytics(string $period, ?string $segment): array
    {
        $dateRange = $this->parsePeriod($period);
        
        // Get engagement events
        $qb = $this->entityManager->createQueryBuilder()
            ->select('e.type, COUNT(e.id) as count, COUNT(DISTINCT r.id) as uniqueRecipients')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\MessageEvent', 'e')
            ->join('e.recipient', 'r')
            ->join('e.message', 'm')
            ->where('e.createdAt >= :startDate')
            ->andWhere('e.createdAt <= :endDate')
            ->setParameter('startDate', $dateRange['start'])
            ->setParameter('endDate', $dateRange['end'])
            ->groupBy('e.type');

        $engagementEvents = $qb->getQuery()->getResult();
        
        // Cohort analysis - group users by their first notification date
        $cohortData = $this->getCohortAnalysis($dateRange, $segment);
        
        // Funnel analysis - conversion through engagement stages
        $funnelData = $this->getFunnelAnalysis($dateRange);
        
        return [
            'period' => $period,
            'segment' => $segment,
            'metrics' => $this->formatEngagementMetrics($engagementEvents),
            'cohortAnalysis' => $cohortData,
            'funnelData' => $funnelData,
            'heatmaps' => $this->getEngagementHeatmaps($dateRange)
        ];
    }

    public function getFailureAnalytics(string $period, ?string $channel, string $groupBy): array
    {
        $dateRange = $this->parsePeriod($period);
        
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m.status, COUNT(m.id) as count')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\Message', 'm')
            ->where('m.status IN (:failureStates)')
            ->andWhere('m.createdAt >= :startDate')
            ->andWhere('m.createdAt <= :endDate')
            ->setParameter('failureStates', ['failed', 'bounced'])
            ->setParameter('startDate', $dateRange['start'])
            ->setParameter('endDate', $dateRange['end']);

        if ($channel) {
            $qb->andWhere('m.type = :channel')
               ->setParameter('channel', $channel);
        }

        switch ($groupBy) {
            case 'channel':
                $qb->addSelect('m.type as channel')
                   ->groupBy('m.status, m.type');
                break;
            case 'transport':
                $qb->addSelect('m.transportName as transport')
                   ->groupBy('m.status, m.transportName');
                break;
            case 'day':
                $qb->addSelect('DATE(m.createdAt) as day')
                   ->groupBy('m.status, DATE(m.createdAt)');
                break;
            default: // reason
                $qb->groupBy('m.status');
        }

        $failures = $qb->getQuery()->getResult();
        
        return [
            'period' => $period,
            'channel' => $channel,
            'groupBy' => $groupBy,
            'failures' => $failures,
            'patterns' => $this->identifyFailurePatterns($failures),
            'recommendations' => $this->generateFailureRecommendations($failures, $channel),
            'trends' => $this->getFailureTrends($dateRange, $channel)
        ];
    }

    public function getCostAnalytics(string $period, string $currency): array
    {
        $dateRange = $this->parsePeriod($period);
        
        // This would typically integrate with billing/cost tracking systems
        // For now, we'll calculate based on message volumes and estimated costs
        $costs = $this->calculateEstimatedCosts($dateRange, $currency);
        
        return [
            'period' => $period,
            'currency' => $currency,
            'costs' => $costs,
            'efficiency' => $this->calculateCostEfficiency($costs),
            'optimization' => $this->generateCostOptimizations($costs),
            'forecasts' => $this->generateCostForecasts($costs, $period)
        ];
    }

    public function getSystemLogs(int $page, int $limit, ?string $level, ?string $channel, string $period): array
    {
        $dateRange = $this->parsePeriod($period);
        $offset = ($page - 1) * $limit;
        
        // This would typically query a logging system or database
        // For demonstration, we'll query message events as system activity
        $qb = $this->entityManager->createQueryBuilder()
            ->select('e.id, e.type, e.createdAt, e.metadata, m.type as channel')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\MessageEvent', 'e')
            ->join('e.message', 'm')
            ->where('e.createdAt >= :startDate')
            ->andWhere('e.createdAt <= :endDate')
            ->setParameter('startDate', $dateRange['start'])
            ->setParameter('endDate', $dateRange['end'])
            ->orderBy('e.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($channel) {
            $qb->andWhere('m.type = :channel')
               ->setParameter('channel', $channel);
        }

        $logs = $qb->getQuery()->getResult();
        $total = $this->getLogCount($dateRange, $level, $channel);
        
        return [
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ];
    }

    public function getQuickSummary(string $period, string $format): array
    {
        $dateRange = $this->parsePeriod($period);
        $summary = $this->getSummaryMetrics($dateRange);
        
        if ($format === 'compact') {
            return [
                'period' => $period,
                'totalNotifications' => $summary['totalNotifications'],
                'deliveryRate' => $summary['deliveryRate'],
                'failureRate' => $summary['failureRate'],
                'topChannel' => $this->getTopPerformingChannel($dateRange),
                'alerts' => $this->getSystemAlerts($dateRange)
            ];
        }
        
        return $summary;
    }

    private function parsePeriod(string $period, string $timezone = 'UTC'): array
    {
        $tz = new \DateTimeZone($timezone);
        $end = new \DateTime('now', $tz);
        
        $start = match ($period) {
            '1d' => (clone $end)->modify('-1 day'),
            '7d' => (clone $end)->modify('-7 days'),
            '30d' => (clone $end)->modify('-30 days'),
            '90d' => (clone $end)->modify('-90 days'),
            '1y' => (clone $end)->modify('-1 year'),
            default => (clone $end)->modify('-30 days'),
        };
        
        return ['start' => $start, 'end' => $end];
    }

    private function getSummaryMetrics(array $dateRange): array
    {
        // Total notifications
        $notificationCount = $this->notificationRepository->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.createdAt >= :start')
            ->andWhere('n.createdAt <= :end')
            ->setParameter('start', $dateRange['start'])
            ->setParameter('end', $dateRange['end'])
            ->getQuery()
            ->getSingleScalarResult();

        // Message statistics
        $messageStats = $this->messageRepository->createQueryBuilder('m')
            ->select('
                COUNT(m.id) as total,
                SUM(CASE WHEN m.status = :sent THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN m.status = :delivered THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN m.status = :failed THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN m.status = :bounced THEN 1 ELSE 0 END) as bounced
            ')
            ->where('m.createdAt >= :start')
            ->andWhere('m.createdAt <= :end')
            ->setParameter('start', $dateRange['start'])
            ->setParameter('end', $dateRange['end'])
            ->setParameter('sent', 'sent')
            ->setParameter('delivered', 'delivered')
            ->setParameter('failed', 'failed')
            ->setParameter('bounced', 'bounced')
            ->getQuery()
            ->getOneOrNullResult();

        // Engagement statistics
        $engagementStats = $this->entityManager->createQueryBuilder()
            ->select('
                COUNT(DISTINCT CASE WHEN e.type = :opened THEN r.id ELSE NULL END) as uniqueOpens,
                COUNT(DISTINCT CASE WHEN e.type = :clicked THEN r.id ELSE NULL END) as uniqueClicks,
                COUNT(CASE WHEN e.type = :opened THEN 1 ELSE NULL END) as totalOpens,
                COUNT(CASE WHEN e.type = :clicked THEN 1 ELSE NULL END) as totalClicks
            ')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\MessageEvent', 'e')
            ->join('e.recipient', 'r')
            ->join('e.message', 'm')
            ->where('e.createdAt >= :start')
            ->andWhere('e.createdAt <= :end')
            ->setParameter('start', $dateRange['start'])
            ->setParameter('end', $dateRange['end'])
            ->setParameter('opened', 'opened')
            ->setParameter('clicked', 'clicked')
            ->getQuery()
            ->getOneOrNullResult();

        $total = $messageStats['total'] ?? 0;
        $delivered = $messageStats['delivered'] ?? 0;
        $failed = ($messageStats['failed'] ?? 0) + ($messageStats['bounced'] ?? 0);
        
        return [
            'totalNotifications' => (int) $notificationCount,
            'totalMessages' => $total,
            'deliveryRate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
            'openRate' => $delivered > 0 ? round((($engagementStats['uniqueOpens'] ?? 0) / $delivered) * 100, 2) : 0,
            'clickRate' => $delivered > 0 ? round((($engagementStats['uniqueClicks'] ?? 0) / $delivered) * 100, 2) : 0,
            'bounceRate' => $total > 0 ? round((($messageStats['bounced'] ?? 0) / $total) * 100, 2) : 0,
            'failureRate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
        ];
    }

    private function getChannelMetrics(array $dateRange, ?string $specificChannel = null): array
    {
        $qb = $this->messageRepository->createQueryBuilder('m')
            ->select('
                m.type as channel,
                COUNT(m.id) as total,
                SUM(CASE WHEN m.status = :sent THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN m.status = :delivered THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN m.status = :failed THEN 1 ELSE 0 END) as failed
            ')
            ->where('m.createdAt >= :start')
            ->andWhere('m.createdAt <= :end')
            ->setParameter('start', $dateRange['start'])
            ->setParameter('end', $dateRange['end'])
            ->setParameter('sent', 'sent')
            ->setParameter('delivered', 'delivered')
            ->setParameter('failed', 'failed')
            ->groupBy('m.type');

        if ($specificChannel) {
            $qb->andWhere('m.type = :channel')
               ->setParameter('channel', $specificChannel);
        }

        $results = $qb->getQuery()->getResult();
        
        $channelData = [];
        foreach ($results as $result) {
            $total = $result['total'];
            $delivered = $result['delivered'];
            
            $channelData[$result['channel']] = [
                'total' => $total,
                'sent' => $result['sent'],
                'delivered' => $delivered,
                'failed' => $result['failed'],
                'deliveryRate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
                'engagementRate' => $this->getChannelEngagementRate($result['channel'], $dateRange),
                'cost' => $this->getEstimatedChannelCost($result['channel'], $total)
            ];
        }
        
        return $specificChannel ? ($channelData[$specificChannel] ?? []) : $channelData;
    }

    private function getChannelEngagementRate(string $channel, array $dateRange): float
    {
        $engagementCount = $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT r.id)')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\MessageEvent', 'e')
            ->join('e.recipient', 'r')
            ->join('e.message', 'm')
            ->where('e.type IN (:engagementTypes)')
            ->andWhere('m.type = :channel')
            ->andWhere('e.createdAt >= :start')
            ->andWhere('e.createdAt <= :end')
            ->setParameter('engagementTypes', ['opened', 'clicked'])
            ->setParameter('channel', $channel)
            ->setParameter('start', $dateRange['start'])
            ->setParameter('end', $dateRange['end'])
            ->getQuery()
            ->getSingleScalarResult();

        $deliveredCount = $this->messageRepository->createQueryBuilder('m')
            ->select('SUM(CASE WHEN m.status = :delivered THEN 1 ELSE 0 END)')
            ->where('m.type = :channel')
            ->andWhere('m.createdAt >= :start')
            ->andWhere('m.createdAt <= :end')
            ->setParameter('channel', $channel)
            ->setParameter('delivered', 'delivered')
            ->setParameter('start', $dateRange['start'])
            ->setParameter('end', $dateRange['end'])
            ->getQuery()
            ->getSingleScalarResult();

        return $deliveredCount > 0 ? round(($engagementCount / $deliveredCount) * 100, 2) : 0;
    }

    private function getEstimatedChannelCost(string $channel, int $messageCount): ?float
    {
        // Estimated costs per message by channel
        $costs = [
            'email' => 0.001,    // $0.001 per email
            'sms' => 0.05,       // $0.05 per SMS
            'push' => 0.0001,    // $0.0001 per push
            'slack' => 0,        // Free for internal
            'telegram' => 0      // Free for bots
        ];

        return isset($costs[$channel]) ? $costs[$channel] * $messageCount : null;
    }

    private function getTrendData(array $dateRange): array
    {
        // This would generate time-series data for charts
        // Simplified implementation
        return [
            'volume' => ['labels' => [], 'datasets' => []],
            'deliveryRates' => ['labels' => [], 'datasets' => []],
            'engagementRates' => ['labels' => [], 'datasets' => []]
        ];
    }

    private function getTopPerformingTypes(array $dateRange): array
    {
        $qb = $this->notificationRepository->createQueryBuilder('n')
            ->select('
                n.type,
                COUNT(n.id) as notificationCount,
                AVG(CASE WHEN m.status = :delivered THEN 1 ELSE 0 END) as avgDeliveryRate
            ')
            ->leftJoin('n.messages', 'm')
            ->where('n.createdAt >= :start')
            ->andWhere('n.createdAt <= :end')
            ->setParameter('start', $dateRange['start'])
            ->setParameter('end', $dateRange['end'])
            ->setParameter('delivered', 'delivered')
            ->groupBy('n.type')
            ->orderBy('avgDeliveryRate', 'DESC')
            ->setMaxResults(5);

        return $qb->getQuery()->getResult();
    }

    // Additional helper methods would be implemented here...
    private function formatDetailedResults(array $results, string $groupBy): array
    {
        // Format results based on groupBy parameter
        return $results;
    }

    private function calculateDetailedSummary(array $results): array
    {
        // Calculate summary from detailed results
        return [];
    }

    private function getBreakdownAnalysis(array $dateRange, ?string $channel): array
    {
        // Detailed breakdown analysis
        return [];
    }

    private function getPreviousPeriod(array $dateRange): array
    {
        $duration = $dateRange['end']->getTimestamp() - $dateRange['start']->getTimestamp();
        return [
            'start' => (clone $dateRange['start'])->modify("-{$duration} seconds"),
            'end' => clone $dateRange['start']
        ];
    }

    private function generateChannelRecommendations(array $channelData): array
    {
        // Generate recommendations based on channel performance
        return [];
    }

    private function getChannelEngagementMetrics(string $channel, array $dateRange): array
    {
        // Detailed engagement metrics for specific channel
        return [];
    }

    private function getChannelTimeSeries(string $channel, array $dateRange): array
    {
        // Time series data for specific channel
        return [];
    }

    private function generateChannelSpecificRecommendations(string $channel, array $statusBreakdown): array
    {
        // Channel-specific recommendations
        return [];
    }

    private function getCohortAnalysis(array $dateRange, ?string $segment): array
    {
        // Cohort analysis implementation
        return [];
    }

    private function getFunnelAnalysis(array $dateRange): array
    {
        // Funnel analysis implementation
        return [];
    }

    private function formatEngagementMetrics(array $engagementEvents): array
    {
        // Format engagement metrics
        return [];
    }

    private function getEngagementHeatmaps(array $dateRange): array
    {
        // Engagement heatmaps
        return [];
    }

    private function identifyFailurePatterns(array $failures): array
    {
        // Identify patterns in failures
        return [];
    }

    private function generateFailureRecommendations(array $failures, ?string $channel): array
    {
        // Generate failure recommendations
        return [];
    }

    private function getFailureTrends(array $dateRange, ?string $channel): array
    {
        // Failure trends over time
        return [];
    }

    private function calculateEstimatedCosts(array $dateRange, string $currency): array
    {
        // Calculate estimated costs
        return [];
    }

    private function calculateCostEfficiency(array $costs): array
    {
        // Calculate cost efficiency metrics
        return [];
    }

    private function generateCostOptimizations(array $costs): array
    {
        // Generate cost optimization recommendations
        return [];
    }

    private function generateCostForecasts(array $costs, string $period): array
    {
        // Generate cost forecasts
        return [];
    }

    private function getLogCount(array $dateRange, ?string $level, ?string $channel): int
    {
        // Get total log count for pagination
        return 0;
    }

    private function getTopPerformingChannel(array $dateRange): string
    {
        // Get top performing channel
        return 'email';
    }

    private function getSystemAlerts(array $dateRange): array
    {
        // Get system alerts
        return [];
    }
}
