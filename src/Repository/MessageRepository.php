<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;

/**
 * @extends ServiceEntityRepository<Message>
 * @method Message|null find($id, $lockMode = null, $lockVersion = null)
 * @method Message|null findOneBy(array $criteria, array $orderBy = null)
 * @method Message[]    findAll()
 * @method Message[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return Message[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.status = :status')
            ->setParameter('status', $status)
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Message[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m INSTANCE OF :type')
            ->setParameter('type', $type)
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Message[]
     */
    public function findByNotification(Notification $notification): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.notification = :notification')
            ->setParameter('notification', $notification)
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Message[]
     */
    public function findPending(): array
    {
        return $this->findByStatus(Message::STATUS_PENDING);
    }

    /**
     * @return Message[]
     */
    public function findQueued(): array
    {
        return $this->findByStatus(Message::STATUS_QUEUED);
    }

    /**
     * @return Message[]
     */
    public function findSent(): array
    {
        return $this->findByStatus(Message::STATUS_SENT);
    }

    /**
     * @return Message[]
     */
    public function findDelivered(): array
    {
        return $this->findByStatus(Message::STATUS_DELIVERED);
    }

    /**
     * @return Message[]
     */
    public function findFailed(): array
    {
        return $this->findByStatus(Message::STATUS_FAILED);
    }

    /**
     * @return Message[]
     */
    public function findByDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.sentAt >= :from')
            ->andWhere('m.sentAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Message[]
     */
    public function findByStatusAndDateRange(string $status, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.status = :status')
            ->andWhere('m.sentAt >= :from')
            ->andWhere('m.sentAt <= :to')
            ->setParameter('status', $status)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find messages that need retry
     * @return Message[]
     */
    public function findForRetry(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.status = :status')
            ->andWhere('m.retryCount < :maxRetries')
            ->setParameter('status', Message::STATUS_FAILED)
            ->setParameter('maxRetries', 3) // Default max retries
            ->orderBy('m.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find messages with attachments
     * @return Message[]
     */
    public function findWithAttachments(): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.attachments', 'a')
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find messages with content
     * @return Message[]
     */
    public function findWithContent(): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.content', 'c')
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find messages with specific event
     * @return Message[]
     */
    public function findWithEvent(string $eventType): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.events', 'e')
            ->andWhere('e.eventType = :eventType')
            ->setParameter('eventType', $eventType)
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search in message content
     * @return Message[]
     */
    public function searchInContent(string $searchTerm): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.content', 'mc')
            ->andWhere('mc.bodyText LIKE :search OR mc.bodyHtml LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByNotification(Notification $notification): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.notification = :notification')
            ->setParameter('notification', $notification)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function getStatsByStatus(): array
    {
        $result = $this->createQueryBuilder('m')
            ->select('m.status, COUNT(m.id) as count')
            ->groupBy('m.status')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['status']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function getStatsByType(): array
    {
        $result = $this->createQueryBuilder('m')
            ->select('SUBSTR(ENTITY_NAME(m), LOCATE(\':\', ENTITY_NAME(m)) + 1) as type, COUNT(m.id) as count')
            ->groupBy('type')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['type']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get retry statistics
     * @return array{pending: int, maxed: int, total: int}
     */
    public function getRetryStats(): array
    {
        $pending = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.status = :status')
            ->andWhere('m.retryCount < :maxRetries')
            ->setParameter('status', Message::STATUS_FAILED)
            ->setParameter('maxRetries', 3)
            ->getQuery()
            ->getSingleScalarResult();

        $maxed = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.status = :status')
            ->andWhere('m.retryCount >= :maxRetries')
            ->setParameter('status', Message::STATUS_FAILED)
            ->setParameter('maxRetries', 3)
            ->getQuery()
            ->getSingleScalarResult();

        $total = $this->countByStatus(Message::STATUS_FAILED);

        return [
            'pending' => (int) $pending,
            'maxed' => (int) $maxed,
            'total' => $total
        ];
    }

    /**
     * Get delivery rate statistics
     * @return array{total: int, delivered: int, sent: int, failed: int, pending: int, rate: float}
     */
    public function getDeliveryRateStats(): array
    {
        $total = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $delivered = $this->countByStatus(Message::STATUS_DELIVERED);
        $sent = $this->countByStatus(Message::STATUS_SENT);
        $failed = $this->countByStatus(Message::STATUS_FAILED);
        $pending = $this->countByStatus(Message::STATUS_PENDING) + $this->countByStatus(Message::STATUS_QUEUED);

        $totalCount = (int) $total;
        $successCount = $delivered + $sent;

        return [
            'total' => $totalCount,
            'delivered' => $delivered,
            'sent' => $sent,
            'failed' => $failed,
            'pending' => $pending,
            'rate' => $totalCount > 0 ? ($successCount / $totalCount) * 100 : 0.0
        ];
    }

    /**
     * Find messages older than specified days
     * @return Message[]
     */
    public function findOlderThan(int $days): array
    {
        $date = (new \DateTimeImmutable())->modify("-{$days} days");
        
        return $this->createQueryBuilder('m')
            ->andWhere('m.sentAt < :date')
            ->setParameter('date', $date)
            ->orderBy('m.sentAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get average processing time for messages
     */
    public function getAverageProcessingTime(): ?float
    {
        $result = $this->createQueryBuilder('m')
            ->select('AVG(TIMESTAMPDIFF(SECOND, m.createdAt, m.sentAt))')
            ->andWhere('m.status = :status')
            ->andWhere('m.sentAt IS NOT NULL')
            ->setParameter('status', Message::STATUS_SENT)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : null;
    }

    /**
     * Clean up old messages
     */
    public function cleanupOldMessages(int $daysOld): int
    {
        $date = (new \DateTimeImmutable())->modify("-{$daysOld} days");
        
        return $this->createQueryBuilder('m')
            ->delete()
            ->andWhere('m.sentAt < :date')
            ->andWhere('m.status IN (:finalStatuses)')
            ->setParameter('date', $date)
            ->setParameter('finalStatuses', [Message::STATUS_DELIVERED, Message::STATUS_FAILED])
            ->getQuery()
            ->execute();
    }

    /**
     * Find a message by its messenger stamp ID
     */
    public function findByStampId(string $stampId): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.messengerStampId = :stampId')
            ->setParameter('stampId', $stampId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find messages by content fingerprint
     *
     * @return Message[]
     */
    public function findByFingerprint(string $fingerprint): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.contentFingerprint = :fingerprint')
            ->setParameter('fingerprint', $fingerprint)
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if a message with the given stamp ID exists
     */
    public function existsByStampId(string $stampId): bool
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.messengerStampId = :stampId')
            ->setParameter('stampId', $stampId)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Find a recent message by content fingerprint to prevent duplicates from direct mailer usage
     */
    public function findRecentByContentFingerprint(string $contentFingerprint, \DateTime $since): ?Message
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.content', 'mc')
            ->andWhere('mc.fingerprint = :fingerprint')
            ->andWhere('m.createdAt >= :since')
            ->setParameter('fingerprint', $contentFingerprint)
            ->setParameter('since', $since)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}