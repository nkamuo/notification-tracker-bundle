<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\SmsMessage;

/**
 * @extends ServiceEntityRepository<SmsMessage>
 * @method SmsMessage|null find($id, $lockMode = null, $lockVersion = null)
 * @method SmsMessage|null findOneBy(array $criteria, array $orderBy = null)
 * @method SmsMessage[]    findAll()
 * @method SmsMessage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SmsMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SmsMessage::class);
    }

    /**
     * @return SmsMessage[]
     */
    public function findByFromNumber(string $fromNumber): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.fromNumber = :fromNumber')
            ->setParameter('fromNumber', $fromNumber)
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SmsMessage[]
     */
    public function findByProviderMessageId(string $providerMessageId): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.providerMessageId = :providerMessageId')
            ->setParameter('providerMessageId', $providerMessageId)
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SmsMessage[]
     */
    public function findByEncoding(string $encoding): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.encoding = :encoding')
            ->setParameter('encoding', $encoding)
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SmsMessage[]
     */
    public function findBySegmentsCount(int $segmentsCount): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.segmentsCount = :segmentsCount')
            ->setParameter('segmentsCount', $segmentsCount)
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SmsMessage[]
     */
    public function findMultiSegment(): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.segmentsCount > 1')
            ->orderBy('sm.segmentsCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SmsMessage[]
     */
    public function findWithCost(): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.cost IS NOT NULL')
            ->orderBy('sm.cost', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SmsMessage[]
     */
    public function findByCostRange(string $minCost, string $maxCost): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.cost >= :minCost')
            ->andWhere('sm.cost <= :maxCost')
            ->setParameter('minCost', $minCost)
            ->setParameter('maxCost', $maxCost)
            ->orderBy('sm.cost', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total cost for all SMS messages
     */
    public function getTotalCost(): ?string
    {
        return $this->createQueryBuilder('sm')
            ->select('SUM(sm.cost)')
            ->andWhere('sm.cost IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get total cost for a specific from number
     */
    public function getTotalCostByFromNumber(string $fromNumber): ?string
    {
        return $this->createQueryBuilder('sm')
            ->select('SUM(sm.cost)')
            ->andWhere('sm.fromNumber = :fromNumber')
            ->andWhere('sm.cost IS NOT NULL')
            ->setParameter('fromNumber', $fromNumber)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get average cost per SMS
     */
    public function getAverageCost(): ?string
    {
        return $this->createQueryBuilder('sm')
            ->select('AVG(sm.cost)')
            ->andWhere('sm.cost IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get statistics by encoding
     * @return array<string, int>
     */
    public function getStatsByEncoding(): array
    {
        $result = $this->createQueryBuilder('sm')
            ->select('sm.encoding, COUNT(sm.id) as count')
            ->andWhere('sm.encoding IS NOT NULL')
            ->groupBy('sm.encoding')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['encoding']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get statistics by segments count
     * @return array<int, int>
     */
    public function getStatsBySegmentsCount(): array
    {
        $result = $this->createQueryBuilder('sm')
            ->select('sm.segmentsCount, COUNT(sm.id) as count')
            ->groupBy('sm.segmentsCount')
            ->orderBy('sm.segmentsCount', 'ASC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[(int) $row['segmentsCount']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get statistics by from number
     * @return array<string, int>
     */
    public function getStatsByFromNumber(): array
    {
        $result = $this->createQueryBuilder('sm')
            ->select('sm.fromNumber, COUNT(sm.id) as count')
            ->andWhere('sm.fromNumber IS NOT NULL')
            ->groupBy('sm.fromNumber')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['fromNumber']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Find delivered SMS messages
     * @return SmsMessage[]
     */
    public function findDelivered(): array
    {
        return $this->createQueryBuilder('sm')
            ->innerJoin('sm.events', 'me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', 'delivered')
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find failed SMS messages
     * @return SmsMessage[]
     */
    public function findFailed(): array
    {
        return $this->createQueryBuilder('sm')
            ->innerJoin('sm.events', 'me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', 'failed')
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get delivery statistics
     * @return array{delivered: int, failed: int, pending: int}
     */
    public function getDeliveryStats(): array
    {
        $delivered = $this->createQueryBuilder('sm')
            ->select('COUNT(DISTINCT sm.id)')
            ->innerJoin('sm.events', 'me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', 'delivered')
            ->getQuery()
            ->getSingleScalarResult();

        $failed = $this->createQueryBuilder('sm')
            ->select('COUNT(DISTINCT sm.id)')
            ->innerJoin('sm.events', 'me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', 'failed')
            ->getQuery()
            ->getSingleScalarResult();

        $total = $this->createQueryBuilder('sm')
            ->select('COUNT(sm.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'delivered' => (int) $delivered,
            'failed' => (int) $failed,
            'pending' => (int) $total - (int) $delivered - (int) $failed
        ];
    }

    /**
     * Find messages sent in date range with cost summary
     * @return array{messages: SmsMessage[], totalCost: string, averageCost: string, messageCount: int}
     */
    public function findWithCostSummaryInDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $messages = $this->createQueryBuilder('sm')
            ->andWhere('sm.sentAt >= :from')
            ->andWhere('sm.sentAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();

        $totalCost = $this->createQueryBuilder('sm')
            ->select('SUM(sm.cost)')
            ->andWhere('sm.sentAt >= :from')
            ->andWhere('sm.sentAt <= :to')
            ->andWhere('sm.cost IS NOT NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $averageCost = $this->createQueryBuilder('sm')
            ->select('AVG(sm.cost)')
            ->andWhere('sm.sentAt >= :from')
            ->andWhere('sm.sentAt <= :to')
            ->andWhere('sm.cost IS NOT NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'messages' => $messages,
            'totalCost' => $totalCost ?? '0',
            'averageCost' => $averageCost ?? '0',
            'messageCount' => count($messages)
        ];
    }
}
