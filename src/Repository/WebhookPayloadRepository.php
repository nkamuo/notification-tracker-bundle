<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\WebhookPayload;

/**
 * @extends ServiceEntityRepository<WebhookPayload>
 * @method WebhookPayload|null find($id, $lockMode = null, $lockVersion = null)
 * @method WebhookPayload|null findOneBy(array $criteria, array $orderBy = null)
 * @method WebhookPayload[]    findAll()
 * @method WebhookPayload[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WebhookPayloadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookPayload::class);
    }

    /**
     * @return WebhookPayload[]
     */
    public function findByProvider(string $provider): array
    {
        return $this->createQueryBuilder('wp')
            ->andWhere('wp.provider = :provider')
            ->setParameter('provider', $provider)
            ->orderBy('wp.receivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WebhookPayload[]
     */
    public function findByEventType(string $eventType): array
    {
        return $this->createQueryBuilder('wp')
            ->andWhere('wp.eventType = :eventType')
            ->setParameter('eventType', $eventType)
            ->orderBy('wp.receivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WebhookPayload[]
     */
    public function findByProviderAndEventType(string $provider, string $eventType): array
    {
        return $this->createQueryBuilder('wp')
            ->andWhere('wp.provider = :provider')
            ->andWhere('wp.eventType = :eventType')
            ->setParameter('provider', $provider)
            ->setParameter('eventType', $eventType)
            ->orderBy('wp.receivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WebhookPayload[]
     */
    public function findUnprocessed(): array
    {
        return $this->createQueryBuilder('wp')
            ->andWhere('wp.processed = :processed')
            ->setParameter('processed', false)
            ->orderBy('wp.receivedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WebhookPayload[]
     */
    public function findProcessed(): array
    {
        return $this->createQueryBuilder('wp')
            ->andWhere('wp.processed = :processed')
            ->setParameter('processed', true)
            ->orderBy('wp.processedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WebhookPayload[]
     */
    public function findUnprocessedByProvider(string $provider): array
    {
        return $this->createQueryBuilder('wp')
            ->andWhere('wp.provider = :provider')
            ->andWhere('wp.processed = :processed')
            ->setParameter('provider', $provider)
            ->setParameter('processed', false)
            ->orderBy('wp.receivedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WebhookPayload[]
     */
    public function findWithErrors(): array
    {
        return $this->createQueryBuilder('wp')
            ->andWhere('wp.processingError IS NOT NULL')
            ->orderBy('wp.receivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WebhookPayload[]
     */
    public function findByDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('wp')
            ->andWhere('wp.receivedAt >= :from')
            ->andWhere('wp.receivedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('wp.receivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WebhookPayload[]
     */
    public function findByProviderAndDateRange(string $provider, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('wp')
            ->andWhere('wp.provider = :provider')
            ->andWhere('wp.receivedAt >= :from')
            ->andWhere('wp.receivedAt <= :to')
            ->setParameter('provider', $provider)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('wp.receivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find payloads older than specified date
     * @return WebhookPayload[]
     */
    public function findOlderThan(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('wp')
            ->andWhere('wp.receivedAt < :date')
            ->setParameter('date', $date)
            ->orderBy('wp.receivedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent unprocessed payloads
     * @return WebhookPayload[]
     */
    public function findRecentUnprocessed(int $limit = 100): array
    {
        return $this->createQueryBuilder('wp')
            ->andWhere('wp.processed = :processed')
            ->setParameter('processed', false)
            ->orderBy('wp.receivedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find payloads with specific signature
     * @return WebhookPayload[]
     */
    public function findBySignature(string $signature): array
    {
        return $this->createQueryBuilder('wp')
            ->andWhere('wp.signature = :signature')
            ->setParameter('signature', $signature)
            ->orderBy('wp.receivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countUnprocessed(): int
    {
        return (int) $this->createQueryBuilder('wp')
            ->select('COUNT(wp.id)')
            ->andWhere('wp.processed = :processed')
            ->setParameter('processed', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countProcessed(): int
    {
        return (int) $this->createQueryBuilder('wp')
            ->select('COUNT(wp.id)')
            ->andWhere('wp.processed = :processed')
            ->setParameter('processed', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countWithErrors(): int
    {
        return (int) $this->createQueryBuilder('wp')
            ->select('COUNT(wp.id)')
            ->andWhere('wp.processingError IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByProvider(string $provider): int
    {
        return (int) $this->createQueryBuilder('wp')
            ->select('COUNT(wp.id)')
            ->andWhere('wp.provider = :provider')
            ->setParameter('provider', $provider)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function getStatsByProvider(): array
    {
        $result = $this->createQueryBuilder('wp')
            ->select('wp.provider, COUNT(wp.id) as count')
            ->groupBy('wp.provider')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['provider']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function getStatsByEventType(): array
    {
        $result = $this->createQueryBuilder('wp')
            ->select('wp.eventType, COUNT(wp.id) as count')
            ->andWhere('wp.eventType IS NOT NULL')
            ->groupBy('wp.eventType')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['eventType']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get processing statistics
     * @return array{processed: int, unprocessed: int, errors: int}
     */
    public function getProcessingStats(): array
    {
        return [
            'processed' => $this->countProcessed(),
            'unprocessed' => $this->countUnprocessed(),
            'errors' => $this->countWithErrors()
        ];
    }

    /**
     * Get provider-specific processing statistics
     * @return array<string, array{processed: int, unprocessed: int, errors: int}>
     */
    public function getProcessingStatsByProvider(): array
    {
        $providers = $this->createQueryBuilder('wp')
            ->select('DISTINCT wp.provider')
            ->getQuery()
            ->getSingleColumnResult();

        $stats = [];
        foreach ($providers as $provider) {
            $processed = $this->createQueryBuilder('wp')
                ->select('COUNT(wp.id)')
                ->andWhere('wp.provider = :provider')
                ->andWhere('wp.processed = :processed')
                ->setParameter('provider', $provider)
                ->setParameter('processed', true)
                ->getQuery()
                ->getSingleScalarResult();

            $unprocessed = $this->createQueryBuilder('wp')
                ->select('COUNT(wp.id)')
                ->andWhere('wp.provider = :provider')
                ->andWhere('wp.processed = :processed')
                ->setParameter('provider', $provider)
                ->setParameter('processed', false)
                ->getQuery()
                ->getSingleScalarResult();

            $errors = $this->createQueryBuilder('wp')
                ->select('COUNT(wp.id)')
                ->andWhere('wp.provider = :provider')
                ->andWhere('wp.processingError IS NOT NULL')
                ->setParameter('provider', $provider)
                ->getQuery()
                ->getSingleScalarResult();

            $stats[$provider] = [
                'processed' => (int) $processed,
                'unprocessed' => (int) $unprocessed,
                'errors' => (int) $errors
            ];
        }

        return $stats;
    }

    /**
     * Mark payload as processed
     */
    public function markAsProcessed(WebhookPayload $payload): void
    {
        $payload->setProcessed(true);
        $this->getEntityManager()->flush();
    }

    /**
     * Mark payload as failed with error
     */
    public function markAsFailed(WebhookPayload $payload, string $error): void
    {
        $payload->setProcessed(true);
        $payload->setProcessingError($error);
        $this->getEntityManager()->flush();
    }

    /**
     * Clean up old processed payloads
     */
    public function cleanupOldPayloads(\DateTimeImmutable $olderThan): int
    {
        $qb = $this->createQueryBuilder('wp')
            ->delete()
            ->andWhere('wp.processed = :processed')
            ->andWhere('wp.receivedAt < :date')
            ->setParameter('processed', true)
            ->setParameter('date', $olderThan);

        return $qb->getQuery()->execute();
    }
}
