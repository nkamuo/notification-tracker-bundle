<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Entity\MessageEvent;

/**
 * @extends ServiceEntityRepository<MessageEvent>
 * @method MessageEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method MessageEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method MessageEvent[]    findAll()
 * @method MessageEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageEvent::class);
    }

    /**
     * @return MessageEvent[]
     */
    public function findByMessage(Message $message): array
    {
        return $this->createQueryBuilder('me')
            ->andWhere('me.message = :message')
            ->setParameter('message', $message)
            ->orderBy('me.occurredAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageEvent[]
     */
    public function findByEventType(string $eventType): array
    {
        return $this->createQueryBuilder('me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', $eventType)
            ->orderBy('me.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageEvent[]
     */
    public function findByMessageAndEventType(Message $message, string $eventType): array
    {
        return $this->createQueryBuilder('me')
            ->andWhere('me.message = :message')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('message', $message)
            ->setParameter('eventType', $eventType)
            ->orderBy('me.occurredAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestByMessage(Message $message): ?MessageEvent
    {
        return $this->createQueryBuilder('me')
            ->andWhere('me.message = :message')
            ->setParameter('message', $message)
            ->orderBy('me.occurredAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestByMessageAndEventType(Message $message, string $eventType): ?MessageEvent
    {
        return $this->createQueryBuilder('me')
            ->andWhere('me.message = :message')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('message', $message)
            ->setParameter('eventType', $eventType)
            ->orderBy('me.occurredAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return MessageEvent[]
     */
    public function findByDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('me')
            ->andWhere('me.occurredAt >= :from')
            ->andWhere('me.occurredAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('me.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageEvent[]
     */
    public function findByEventTypeAndDateRange(string $eventType, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('me')
            ->andWhere('me.eventType = :eventType')
            ->andWhere('me.occurredAt >= :from')
            ->andWhere('me.occurredAt <= :to')
            ->setParameter('eventType', $eventType)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('me.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByEventType(string $eventType): int
    {
        return (int) $this->createQueryBuilder('me')
            ->select('COUNT(me.id)')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', $eventType)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByMessage(Message $message): int
    {
        return (int) $this->createQueryBuilder('me')
            ->select('COUNT(me.id)')
            ->andWhere('me.message = :message')
            ->setParameter('message', $message)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function getEventStatsByType(): array
    {
        $result = $this->createQueryBuilder('me')
            ->select('me.eventType, COUNT(me.id) as count')
            ->groupBy('me.eventType')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['eventType']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get event statistics for a specific date range
     * @return array<string, int>
     */
    public function getEventStatsByTypeForDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $result = $this->createQueryBuilder('me')
            ->select('me.eventType, COUNT(me.id) as count')
            ->andWhere('me.occurredAt >= :from')
            ->andWhere('me.occurredAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('me.eventType')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['eventType']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Find events by provider data
     * @return MessageEvent[]
     */
    public function findByProviderReference(string $providerReference): array
    {
        return $this->createQueryBuilder('me')
            ->andWhere('JSON_EXTRACT(me.providerData, \'$.reference\') = :reference')
            ->setParameter('reference', $providerReference)
            ->orderBy('me.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the latest event for each message
     * @return MessageEvent[]
     */
    public function findLatestEventsPerMessage(): array
    {
        return $this->createQueryBuilder('me')
            ->innerJoin(
                MessageEvent::class,
                'me2',
                'WITH',
                'me.message = me2.message AND me.occurredAt = (
                    SELECT MAX(me3.occurredAt) 
                    FROM ' . MessageEvent::class . ' me3 
                    WHERE me3.message = me.message
                )'
            )
            ->orderBy('me.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
