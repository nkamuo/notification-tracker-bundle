<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\ApiResource\QueueResource;
use Nkamuo\NotificationTrackerBundle\Entity\QueuedMessage;
use Nkamuo\NotificationTrackerBundle\Repository\QueuedMessageRepository;

class QueueResourceStateProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private QueuedMessageRepository $queuedMessageRepository
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Check if this is an item operation (has ID)
        if (isset($uriVariables['id'])) {
            return $this->getQueuedMessage($uriVariables['id']);
        }

        // Check operation class name for special operations
        $operationName = $operation->getName();
        
        return match ($operationName) {
            'api_queue_get_collection' => $this->getQueuedMessages($context),
            'stats' => $this->getStats(),
            'health' => $this->getHealth(),
            default => $this->getQueuedMessages($context),
        };
    }

    private function getQueuedMessage(string $id): ?QueueResource
    {
        $queuedMessage = $this->queuedMessageRepository->find($id);
        
        if (!$queuedMessage) {
            return null;
        }

        return QueueResource::fromEntity($queuedMessage);
    }

    private function getQueuedMessages(array $context): array
    {
        $page = $context['filters']['page'] ?? 1;
        $limit = $context['filters']['limit'] ?? 50;
        $transport = $context['filters']['transport'] ?? null;
        $status = $context['filters']['status'] ?? null;
        $provider = $context['filters']['provider'] ?? null;

        $qb = $this->queuedMessageRepository->createQueryBuilder('qm')
            ->orderBy('qm.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($transport) {
            $qb->andWhere('qm.transport = :transport')
               ->setParameter('transport', $transport);
        }

        if ($status) {
            $qb->andWhere('qm.status = :status')
               ->setParameter('status', $status);
        }

        if ($provider) {
            $qb->andWhere('qm.notificationProvider = :provider')
               ->setParameter('provider', $provider);
        }

        $queuedMessages = $qb->getQuery()->getResult();

        return array_map(
            fn(QueuedMessage $qm) => QueueResource::fromEntity($qm),
            $queuedMessages
        );
    }

    public function getStats(): QueueResource
    {
        $stats = $this->calculateQueueStats();
        return QueueResource::createStatsResource($stats);
    }

    public function getHealth(): QueueResource
    {
        $health = $this->calculateQueueHealth();
        return QueueResource::createHealthResource($health);
    }

    private function calculateQueueStats(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_messages,
                COUNT(CASE WHEN status = 'queued' THEN 1 END) as queued_messages,
                COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_messages,
                COUNT(CASE WHEN status = 'processed' THEN 1 END) as processed_messages,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_messages,
                COUNT(CASE WHEN status = 'retrying' THEN 1 END) as retrying_messages,
                AVG(CASE 
                    WHEN processed_at IS NOT NULL AND delivered_at IS NOT NULL 
                    THEN TIMESTAMPDIFF(MICROSECOND, delivered_at, processed_at) / 1000000.0 
                END) as average_processing_time,
                (COUNT(CASE WHEN status = 'processed' THEN 1 END) * 100.0 / COUNT(*)) as success_rate
            FROM notification_queued_messages
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ";

        $result = $this->connection->executeQuery($sql)->fetchAssociative();

        // Get messages by transport
        $transportSql = "
            SELECT transport, COUNT(*) as count
            FROM notification_queued_messages 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY transport
        ";
        $transportResults = $this->connection->executeQuery($transportSql)->fetchAllAssociative();
        $messagesByTransport = array_column($transportResults, 'count', 'transport');

        // Get messages by provider
        $providerSql = "
            SELECT notification_provider, COUNT(*) as count
            FROM notification_queued_messages 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND notification_provider IS NOT NULL
            GROUP BY notification_provider
        ";
        $providerResults = $this->connection->executeQuery($providerSql)->fetchAllAssociative();
        $messagesByProvider = array_column($providerResults, 'count', 'notification_provider');

        return [
            'total_messages' => (int) $result['total_messages'],
            'queued_messages' => (int) $result['queued_messages'],
            'delivered_messages' => (int) $result['delivered_messages'],
            'processed_messages' => (int) $result['processed_messages'],
            'failed_messages' => (int) $result['failed_messages'],
            'retrying_messages' => (int) $result['retrying_messages'],
            'messages_by_transport' => $messagesByTransport,
            'messages_by_provider' => $messagesByProvider,
            'average_processing_time' => (float) ($result['average_processing_time'] ?? 0),
            'success_rate' => (float) ($result['success_rate'] ?? 0),
        ];
    }

    private function calculateQueueHealth(): array
    {
        $healthChecks = [];
        $overallHealth = 'healthy';

        // Check for stuck messages (queued for more than 1 hour)
        $stuckMessagesSql = "
            SELECT COUNT(*) as stuck_count
            FROM notification_queued_messages 
            WHERE status = 'queued' 
            AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ";
        $stuckCount = (int) $this->connection->executeQuery($stuckMessagesSql)->fetchOne();

        if ($stuckCount > 0) {
            $healthChecks[] = [
                'name' => 'Stuck Messages',
                'status' => 'warning',
                'message' => "Found {$stuckCount} messages queued for more than 1 hour"
            ];
            $overallHealth = 'warning';
        }

        // Check oldest queued message age
        $oldestMessageSql = "
            SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) as age_seconds
            FROM notification_queued_messages 
            WHERE status = 'queued'
            ORDER BY created_at ASC 
            LIMIT 1
        ";
        $oldestAge = $this->connection->executeQuery($oldestMessageSql)->fetchOne();
        $oldestAge = $oldestAge ? (int) $oldestAge : 0;

        if ($oldestAge > 3600) { // 1 hour
            $healthChecks[] = [
                'name' => 'Queue Age',
                'status' => 'critical',
                'message' => 'Oldest queued message is ' . gmdate('H:i:s', $oldestAge) . ' old'
            ];
            $overallHealth = 'critical';
        }

        // Check transport health by status
        $transportHealthSql = "
            SELECT 
                transport,
                COUNT(CASE WHEN status = 'queued' THEN 1 END) as queued,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                COUNT(*) as total
            FROM notification_queued_messages 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY transport
        ";
        $transportResults = $this->connection->executeQuery($transportHealthSql)->fetchAllAssociative();
        
        $transportHealth = [];
        foreach ($transportResults as $result) {
            $failureRate = $result['total'] > 0 ? ($result['failed'] / $result['total']) * 100 : 0;
            $status = $failureRate > 50 ? 'critical' : ($failureRate > 20 ? 'warning' : 'healthy');
            
            $transportHealth[$result['transport']] = [
                'status' => $status,
                'queued' => (int) $result['queued'],
                'failed' => (int) $result['failed'],
                'total' => (int) $result['total'],
                'failure_rate' => round($failureRate, 2)
            ];

            if ($status === 'critical') {
                $overallHealth = 'critical';
            } elseif ($status === 'warning' && $overallHealth === 'healthy') {
                $overallHealth = 'warning';
            }
        }

        return [
            'overall_health' => $overallHealth,
            'transport_health' => $transportHealth,
            'oldest_queued_message_age' => $oldestAge,
            'stuck_messages_count' => $stuckCount,
            'health_checks' => $healthChecks,
        ];
    }
}
