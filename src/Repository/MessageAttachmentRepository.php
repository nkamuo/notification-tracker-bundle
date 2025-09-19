<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Entity\MessageAttachment;

/**
 * @extends ServiceEntityRepository<MessageAttachment>
 * @method MessageAttachment|null find($id, $lockMode = null, $lockVersion = null)
 * @method MessageAttachment|null findOneBy(array $criteria, array $orderBy = null)
 * @method MessageAttachment[]    findAll()
 * @method MessageAttachment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageAttachment::class);
    }

    /**
     * @return MessageAttachment[]
     */
    public function findByMessage(Message $message): array
    {
        return $this->createQueryBuilder('ma')
            ->andWhere('ma.message = :message')
            ->setParameter('message', $message)
            ->orderBy('ma.filename', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageAttachment[]
     */
    public function findByContentType(string $contentType): array
    {
        return $this->createQueryBuilder('ma')
            ->andWhere('ma.contentType = :contentType')
            ->setParameter('contentType', $contentType)
            ->orderBy('ma.filename', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageAttachment[]
     */
    public function findByContentTypePattern(string $pattern): array
    {
        return $this->createQueryBuilder('ma')
            ->andWhere('ma.contentType LIKE :pattern')
            ->setParameter('pattern', $pattern)
            ->orderBy('ma.filename', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find attachments larger than specified size in bytes
     * @return MessageAttachment[]
     */
    public function findLargerThan(int $sizeInBytes): array
    {
        return $this->createQueryBuilder('ma')
            ->andWhere('ma.size > :size')
            ->setParameter('size', $sizeInBytes)
            ->orderBy('ma.size', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find attachments smaller than specified size in bytes
     * @return MessageAttachment[]
     */
    public function findSmallerThan(int $sizeInBytes): array
    {
        return $this->createQueryBuilder('ma')
            ->andWhere('ma.size < :size')
            ->setParameter('size', $sizeInBytes)
            ->orderBy('ma.size', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find attachments by filename pattern
     * @return MessageAttachment[]
     */
    public function findByFilenamePattern(string $pattern): array
    {
        return $this->createQueryBuilder('ma')
            ->andWhere('ma.filename LIKE :pattern')
            ->setParameter('pattern', $pattern)
            ->orderBy('ma.filename', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find attachments with content ID (for inline attachments)
     * @return MessageAttachment[]
     */
    public function findWithContentId(): array
    {
        return $this->createQueryBuilder('ma')
            ->andWhere('ma.contentId IS NOT NULL')
            ->orderBy('ma.contentId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find inline attachments for a message
     * @return MessageAttachment[]
     */
    public function findInlineByMessage(Message $message): array
    {
        return $this->createQueryBuilder('ma')
            ->andWhere('ma.message = :message')
            ->andWhere('ma.contentId IS NOT NULL')
            ->setParameter('message', $message)
            ->orderBy('ma.contentId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find regular (non-inline) attachments for a message
     * @return MessageAttachment[]
     */
    public function findRegularByMessage(Message $message): array
    {
        return $this->createQueryBuilder('ma')
            ->andWhere('ma.message = :message')
            ->andWhere('ma.contentId IS NULL')
            ->setParameter('message', $message)
            ->orderBy('ma.filename', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByMessage(Message $message): int
    {
        return (int) $this->createQueryBuilder('ma')
            ->select('COUNT(ma.id)')
            ->andWhere('ma.message = :message')
            ->setParameter('message', $message)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalSizeByMessage(Message $message): int
    {
        $result = $this->createQueryBuilder('ma')
            ->select('SUM(ma.size)')
            ->andWhere('ma.message = :message')
            ->setParameter('message', $message)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get attachment statistics by content type
     * @return array<string, array{count: int, totalSize: int}>
     */
    public function getStatsByContentType(): array
    {
        $result = $this->createQueryBuilder('ma')
            ->select('ma.contentType, COUNT(ma.id) as count, SUM(ma.size) as totalSize')
            ->groupBy('ma.contentType')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['contentType']] = [
                'count' => (int) $row['count'],
                'totalSize' => (int) ($row['totalSize'] ?? 0)
            ];
        }

        return $stats;
    }

    /**
     * Find orphaned attachments (messages that no longer exist)
     * @return MessageAttachment[]
     */
    public function findOrphaned(): array
    {
        return $this->createQueryBuilder('ma')
            ->leftJoin('ma.message', 'm')
            ->andWhere('m.id IS NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find attachments with stored file paths
     * @return MessageAttachment[]
     */
    public function findWithStoredFiles(): array
    {
        return $this->createQueryBuilder('ma')
            ->andWhere('ma.path IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find attachments stored as content in database
     * @return MessageAttachment[]
     */
    public function findWithInlineContent(): array
    {
        return $this->createQueryBuilder('ma')
            ->andWhere('ma.content IS NOT NULL')
            ->getQuery()
            ->getResult();
    }
}
