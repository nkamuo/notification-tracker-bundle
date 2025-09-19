<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;

/**
 * @extends ServiceEntityRepository<Notification>
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return Notification[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.type = :type')
            ->setParameter('type', $type)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Notification[]
     */
    public function findByImportance(string $importance): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.importance = :importance')
            ->setParameter('importance', $importance)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Notification[]
     */
    public function findByDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.createdAt >= :from')
            ->andWhere('n.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Notification[]
     */
    public function findRecentByImportance(string $importance, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.importance = :importance')
            ->setParameter('importance', $importance)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByType(string $type): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByImportance(string $importance): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.importance = :importance')
            ->setParameter('importance', $importance)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function getNotificationStatsByType(): array
    {
        $result = $this->createQueryBuilder('n')
            ->select('n.type, COUNT(n.id) as count')
            ->groupBy('n.type')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['type']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function getNotificationStatsByImportance(): array
    {
        $result = $this->createQueryBuilder('n')
            ->select('n.importance, COUNT(n.id) as count')
            ->groupBy('n.importance')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['importance']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Find notifications with their message count
     * @return array<array{notification: Notification, messageCount: int}>
     */
    public function findWithMessageCount(): array
    {
        $result = $this->createQueryBuilder('n')
            ->select('n, COUNT(m.id) as messageCount')
            ->leftJoin('n.messages', 'm')
            ->groupBy('n.id')
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(function ($row) {
            return [
                'notification' => $row[0],
                'messageCount' => (int) $row['messageCount']
            ];
        }, $result);
    }
}
