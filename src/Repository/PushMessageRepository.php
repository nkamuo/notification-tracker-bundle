<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\PushMessage;

/**
 * @extends ServiceEntityRepository<PushMessage>
 * @method PushMessage|null find($id, $lockMode = null, $lockVersion = null)
 * @method PushMessage|null findOneBy(array $criteria, array $orderBy = null)
 * @method PushMessage[]    findAll()
 * @method PushMessage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PushMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushMessage::class);
    }

    /**
     * @return PushMessage[]
     */
    public function findByTitle(string $title): array
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('pm.title = :title')
            ->setParameter('title', $title)
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PushMessage[]
     */
    public function findByTitlePattern(string $pattern): array
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('pm.title LIKE :pattern')
            ->setParameter('pattern', $pattern)
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PushMessage[]
     */
    public function findByPlatform(string $platform): array
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('pm.platform = :platform')
            ->setParameter('platform', $platform)
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PushMessage[]
     */
    public function findBySound(string $sound): array
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('pm.sound = :sound')
            ->setParameter('sound', $sound)
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PushMessage[]
     */
    public function findWithIcon(): array
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('pm.icon IS NOT NULL')
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PushMessage[]
     */
    public function findWithImage(): array
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('pm.image IS NOT NULL')
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PushMessage[]
     */
    public function findWithBadge(): array
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('pm.badge IS NOT NULL')
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PushMessage[]
     */
    public function findWithClickAction(): array
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('pm.clickAction IS NOT NULL')
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PushMessage[]
     */
    public function findWithCustomData(): array
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('JSON_LENGTH(pm.customData) > 0')
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search in title and body
     * @return PushMessage[]
     */
    public function searchInTitleAndBody(string $searchTerm): array
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('pm.title LIKE :search OR pm.body LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find messages with specific custom data key
     * @return PushMessage[]
     */
    public function findByCustomDataKey(string $key): array
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('JSON_EXTRACT(pm.customData, :keyPath) IS NOT NULL')
            ->setParameter('keyPath', '$."' . $key . '"')
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find messages with specific custom data value
     * @return PushMessage[]
     */
    public function findByCustomDataValue(string $key, string $value): array
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('JSON_EXTRACT(pm.customData, :keyPath) = :value')
            ->setParameter('keyPath', '$."' . $key . '"')
            ->setParameter('value', $value)
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get statistics by platform
     * @return array<string, int>
     */
    public function getStatsByPlatform(): array
    {
        $result = $this->createQueryBuilder('pm')
            ->select('pm.platform, COUNT(pm.id) as count')
            ->andWhere('pm.platform IS NOT NULL')
            ->groupBy('pm.platform')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['platform']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get statistics by sound
     * @return array<string, int>
     */
    public function getStatsBySound(): array
    {
        $result = $this->createQueryBuilder('pm')
            ->select('pm.sound, COUNT(pm.id) as count')
            ->andWhere('pm.sound IS NOT NULL')
            ->groupBy('pm.sound')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['sound']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get media usage statistics
     * @return array{withIcon: int, withImage: int, withBadge: int, plain: int}
     */
    public function getMediaStats(): array
    {
        $withIcon = $this->createQueryBuilder('pm')
            ->select('COUNT(pm.id)')
            ->andWhere('pm.icon IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $withImage = $this->createQueryBuilder('pm')
            ->select('COUNT(pm.id)')
            ->andWhere('pm.image IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $withBadge = $this->createQueryBuilder('pm')
            ->select('COUNT(pm.id)')
            ->andWhere('pm.badge IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $plain = $this->createQueryBuilder('pm')
            ->select('COUNT(pm.id)')
            ->andWhere('pm.icon IS NULL')
            ->andWhere('pm.image IS NULL')
            ->andWhere('pm.badge IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'withIcon' => (int) $withIcon,
            'withImage' => (int) $withImage,
            'withBadge' => (int) $withBadge,
            'plain' => (int) $plain
        ];
    }

    /**
     * Get interactive features statistics
     * @return array{withClickAction: int, withCustomData: int, basic: int}
     */
    public function getInteractiveStats(): array
    {
        $withClickAction = $this->createQueryBuilder('pm')
            ->select('COUNT(pm.id)')
            ->andWhere('pm.clickAction IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $withCustomData = $this->createQueryBuilder('pm')
            ->select('COUNT(pm.id)')
            ->andWhere('JSON_LENGTH(pm.customData) > 0')
            ->getQuery()
            ->getSingleScalarResult();

        $basic = $this->createQueryBuilder('pm')
            ->select('COUNT(pm.id)')
            ->andWhere('pm.clickAction IS NULL')
            ->andWhere('JSON_LENGTH(pm.customData) = 0 OR pm.customData IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'withClickAction' => (int) $withClickAction,
            'withCustomData' => (int) $withCustomData,
            'basic' => (int) $basic
        ];
    }

    /**
     * Find delivered push messages
     * @return PushMessage[]
     */
    public function findDelivered(): array
    {
        return $this->createQueryBuilder('pm')
            ->innerJoin('pm.events', 'me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', 'delivered')
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find opened push messages
     * @return PushMessage[]
     */
    public function findOpened(): array
    {
        return $this->createQueryBuilder('pm')
            ->innerJoin('pm.events', 'me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', 'opened')
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find clicked push messages
     * @return PushMessage[]
     */
    public function findClicked(): array
    {
        return $this->createQueryBuilder('pm')
            ->innerJoin('pm.events', 'me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', 'clicked')
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get engagement statistics
     * @return array{delivered: int, opened: int, clicked: int, openRate: float, clickRate: float}
     */
    public function getEngagementStats(): array
    {
        $delivered = $this->createQueryBuilder('pm')
            ->select('COUNT(DISTINCT pm.id)')
            ->innerJoin('pm.events', 'me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', 'delivered')
            ->getQuery()
            ->getSingleScalarResult();

        $opened = $this->createQueryBuilder('pm')
            ->select('COUNT(DISTINCT pm.id)')
            ->innerJoin('pm.events', 'me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', 'opened')
            ->getQuery()
            ->getSingleScalarResult();

        $clicked = $this->createQueryBuilder('pm')
            ->select('COUNT(DISTINCT pm.id)')
            ->innerJoin('pm.events', 'me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', 'clicked')
            ->getQuery()
            ->getSingleScalarResult();

        $deliveredCount = (int) $delivered;
        $openedCount = (int) $opened;
        $clickedCount = (int) $clicked;

        return [
            'delivered' => $deliveredCount,
            'opened' => $openedCount,
            'clicked' => $clickedCount,
            'openRate' => $deliveredCount > 0 ? ($openedCount / $deliveredCount) * 100 : 0.0,
            'clickRate' => $openedCount > 0 ? ($clickedCount / $openedCount) * 100 : 0.0
        ];
    }

    /**
     * Find messages by platform and date range
     * @return PushMessage[]
     */
    public function findByPlatformAndDateRange(string $platform, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('pm.platform = :platform')
            ->andWhere('pm.sentAt >= :from')
            ->andWhere('pm.sentAt <= :to')
            ->setParameter('platform', $platform)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('pm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get platform activity in date range
     * @return array<string, int>
     */
    public function getPlatformActivityInDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $result = $this->createQueryBuilder('pm')
            ->select('pm.platform, COUNT(pm.id) as count')
            ->andWhere('pm.sentAt >= :from')
            ->andWhere('pm.sentAt <= :to')
            ->andWhere('pm.platform IS NOT NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('pm.platform')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['platform']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Find most common titles
     * @return array<string, int>
     */
    public function getMostCommonTitles(int $limit = 10): array
    {
        $result = $this->createQueryBuilder('pm')
            ->select('pm.title, COUNT(pm.id) as count')
            ->groupBy('pm.title')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['title']] = (int) $row['count'];
        }

        return $stats;
    }
}
