<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Entity\MessageRecipient;

/**
 * @extends ServiceEntityRepository<MessageRecipient>
 * @method MessageRecipient|null find($id, $lockMode = null, $lockVersion = null)
 * @method MessageRecipient|null findOneBy(array $criteria, array $orderBy = null)
 * @method MessageRecipient[]    findAll()
 * @method MessageRecipient[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageRecipientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageRecipient::class);
    }

    /**
     * @return MessageRecipient[]
     */
    public function findByMessage(Message $message): array
    {
        return $this->createQueryBuilder('mr')
            ->andWhere('mr.message = :message')
            ->setParameter('message', $message)
            ->orderBy('mr.type', 'ASC')
            ->addOrderBy('mr.address', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageRecipient[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('mr')
            ->andWhere('mr.type = :type')
            ->setParameter('type', $type)
            ->orderBy('mr.address', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageRecipient[]
     */
    public function findByMessageAndType(Message $message, string $type): array
    {
        return $this->createQueryBuilder('mr')
            ->andWhere('mr.message = :message')
            ->andWhere('mr.type = :type')
            ->setParameter('message', $message)
            ->setParameter('type', $type)
            ->orderBy('mr.address', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageRecipient[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('mr')
            ->andWhere('mr.status = :status')
            ->setParameter('status', $status)
            ->orderBy('mr.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageRecipient[]
     */
    public function findByAddress(string $address): array
    {
        return $this->createQueryBuilder('mr')
            ->andWhere('mr.address = :address')
            ->setParameter('address', $address)
            ->orderBy('mr.message', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageRecipient[]
     */
    public function findByAddressPattern(string $pattern): array
    {
        return $this->createQueryBuilder('mr')
            ->andWhere('mr.address LIKE :pattern')
            ->setParameter('pattern', $pattern)
            ->orderBy('mr.address', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageRecipient[]
     */
    public function findByMessageAndStatus(Message $message, string $status): array
    {
        return $this->createQueryBuilder('mr')
            ->andWhere('mr.message = :message')
            ->andWhere('mr.status = :status')
            ->setParameter('message', $message)
            ->setParameter('status', $status)
            ->orderBy('mr.type', 'ASC')
            ->addOrderBy('mr.address', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find primary recipients (TO) for a message
     * @return MessageRecipient[]
     */
    public function findPrimaryByMessage(Message $message): array
    {
        return $this->findByMessageAndType($message, MessageRecipient::TYPE_TO);
    }

    /**
     * Find carbon copy recipients (CC) for a message
     * @return MessageRecipient[]
     */
    public function findCarbonCopyByMessage(Message $message): array
    {
        return $this->findByMessageAndType($message, MessageRecipient::TYPE_CC);
    }

    /**
     * Find blind carbon copy recipients (BCC) for a message
     * @return MessageRecipient[]
     */
    public function findBlindCarbonCopyByMessage(Message $message): array
    {
        return $this->findByMessageAndType($message, MessageRecipient::TYPE_BCC);
    }

    public function countByMessage(Message $message): int
    {
        return (int) $this->createQueryBuilder('mr')
            ->select('COUNT(mr.id)')
            ->andWhere('mr.message = :message')
            ->setParameter('message', $message)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByMessageAndType(Message $message, string $type): int
    {
        return (int) $this->createQueryBuilder('mr')
            ->select('COUNT(mr.id)')
            ->andWhere('mr.message = :message')
            ->andWhere('mr.type = :type')
            ->setParameter('message', $message)
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('mr')
            ->select('COUNT(mr.id)')
            ->andWhere('mr.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function getStatsByType(): array
    {
        $result = $this->createQueryBuilder('mr')
            ->select('mr.type, COUNT(mr.id) as count')
            ->groupBy('mr.type')
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
    public function getStatsByStatus(): array
    {
        $result = $this->createQueryBuilder('mr')
            ->select('mr.status, COUNT(mr.id) as count')
            ->groupBy('mr.status')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['status']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Find recipients that have bounced
     * @return MessageRecipient[]
     */
    public function findBounced(): array
    {
        return $this->findByStatus(MessageRecipient::STATUS_BOUNCED);
    }

    /**
     * Find recipients that have complained
     * @return MessageRecipient[]
     */
    public function findComplained(): array
    {
        return $this->findByStatus(MessageRecipient::STATUS_COMPLAINED);
    }

    /**
     * Find recipients that have unsubscribed
     * @return MessageRecipient[]
     */
    public function findUnsubscribed(): array
    {
        return $this->findByStatus(MessageRecipient::STATUS_UNSUBSCRIBED);
    }

    /**
     * Find all recipients for an address across all messages
     * @return MessageRecipient[]
     */
    public function findRecipientHistory(string $address): array
    {
        return $this->createQueryBuilder('mr')
            ->andWhere('mr.address = :address')
            ->setParameter('address', $address)
            ->orderBy('mr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if an address has bounced or complained
     */
    public function hasNegativeHistory(string $address): bool
    {
        $count = $this->createQueryBuilder('mr')
            ->select('COUNT(mr.id)')
            ->andWhere('mr.address = :address')
            ->andWhere('mr.status IN (:negativeStatuses)')
            ->setParameter('address', $address)
            ->setParameter('negativeStatuses', [
                MessageRecipient::STATUS_BOUNCED,
                MessageRecipient::STATUS_COMPLAINED,
                MessageRecipient::STATUS_UNSUBSCRIBED
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
