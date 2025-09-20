<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\QueuedMessage;

/**
 * @extends ServiceEntityRepository<QueuedMessage>
 */
class QueuedMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QueuedMessage::class);
    }

    /**
     * Find available messages for processing
     */
    public function findAvailableMessages(string $transport, string $queueName, int $limit = 10): array
    {
        return $this->createQueryBuilder('qm')
            ->where('qm.transport = :transport')
            ->andWhere('qm.queueName = :queueName')
            ->andWhere('qm.status = :status')
            ->andWhere('qm.availableAt IS NULL OR qm.availableAt <= :now')
            ->andWhere('qm.deliveredAt IS NULL')
            ->orderBy('qm.priority', 'DESC')
            ->addOrderBy('qm.createdAt', 'ASC')
            ->setParameter('transport', $transport)
            ->setParameter('queueName', $queueName)
            ->setParameter('status', 'queued')
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find messages available for retry
     */
    public function findRetryableMessages(string $transport, string $queueName, int $limit = 10): array
    {
        return $this->createQueryBuilder('qm')
            ->where('qm.transport = :transport')
            ->andWhere('qm.queueName = :queueName')
            ->andWhere('qm.status = :status')
            ->andWhere('qm.availableAt IS NOT NULL AND qm.availableAt <= :now')
            ->andWhere('qm.retryCount < qm.maxRetries')
            ->orderBy('qm.priority', 'DESC')
            ->addOrderBy('qm.availableAt', 'ASC')
            ->setParameter('transport', $transport)
            ->setParameter('queueName', $queueName)
            ->setParameter('status', 'retrying')
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count messages by status
     */
    public function countByStatus(string $transport, string $queueName = null): array
    {
        $qb = $this->createQueryBuilder('qm')
            ->select('qm.status, COUNT(qm.id) as count')
            ->where('qm.transport = :transport')
            ->groupBy('qm.status')
            ->setParameter('transport', $transport);

        if ($queueName !== null) {
            $qb->andWhere('qm.queueName = :queueName')
               ->setParameter('queueName', $queueName);
        }

        $results = $qb->getQuery()->getResult();
        
        $counts = [];
        foreach ($results as $result) {
            $counts[$result['status']] = (int) $result['count'];
        }

        return $counts;
    }

    /**
     * Find stuck messages (delivered but not processed after timeout)
     */
    public function findStuckMessages(\DateTimeImmutable $timeout): array
    {
        return $this->createQueryBuilder('qm')
            ->where('qm.deliveredAt IS NOT NULL')
            ->andWhere('qm.processedAt IS NULL')
            ->andWhere('qm.deliveredAt < :timeout')
            ->andWhere('qm.status NOT IN (:finalStates)')
            ->setParameter('timeout', $timeout)
            ->setParameter('finalStates', ['processed', 'failed'])
            ->getQuery()
            ->getResult();
    }

    /**
     * Clean up old processed messages
     */
    public function cleanupOldMessages(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('qm')
            ->delete()
            ->where('qm.status IN (:finalStates)')
            ->andWhere('qm.processedAt < :before')
            ->setParameter('finalStates', ['processed', 'failed'])
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    /**
     * Get queue statistics
     */
    public function getQueueStatistics(string $transport, string $queueName = null): array
    {
        $qb = $this->createQueryBuilder('qm')
            ->select([
                'COUNT(qm.id) as total',
                'AVG(qm.retryCount) as avgRetries',
                'MAX(qm.retryCount) as maxRetries',
                'COUNT(CASE WHEN qm.status = \'queued\' THEN 1 END) as queued',
                'COUNT(CASE WHEN qm.status = \'delivered\' THEN 1 END) as delivered',
                'COUNT(CASE WHEN qm.status = \'processed\' THEN 1 END) as processed',
                'COUNT(CASE WHEN qm.status = \'failed\' THEN 1 END) as failed',
                'COUNT(CASE WHEN qm.status = \'retrying\' THEN 1 END) as retrying'
            ])
            ->where('qm.transport = :transport')
            ->setParameter('transport', $transport);

        if ($queueName !== null) {
            $qb->andWhere('qm.queueName = :queueName')
               ->setParameter('queueName', $queueName);
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'average_retries' => (float) $result['avgRetries'],
            'max_retries' => (int) $result['maxRetries'],
            'queued' => (int) $result['queued'],
            'delivered' => (int) $result['delivered'],
            'processed' => (int) $result['processed'],
            'failed' => (int) $result['failed'],
            'retrying' => (int) $result['retrying'],
        ];
    }

    /**
     * Get messages by provider for analytics
     */
    public function getMessagesByProvider(string $transport, \DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('qm')
            ->select('qm.notificationProvider, COUNT(qm.id) as count')
            ->where('qm.transport = :transport')
            ->andWhere('qm.notificationProvider IS NOT NULL')
            ->groupBy('qm.notificationProvider')
            ->setParameter('transport', $transport);

        if ($since !== null) {
            $qb->andWhere('qm.createdAt >= :since')
               ->setParameter('since', $since);
        }

        $results = $qb->getQuery()->getResult();
        
        $counts = [];
        foreach ($results as $result) {
            $counts[$result['notificationProvider']] = (int) $result['count'];
        }

        return $counts;
    }
}
