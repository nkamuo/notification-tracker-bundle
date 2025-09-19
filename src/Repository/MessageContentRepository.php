<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Entity\MessageContent;

/**
 * @extends ServiceEntityRepository<MessageContent>
 * @method MessageContent|null find($id, $lockMode = null, $lockVersion = null)
 * @method MessageContent|null findOneBy(array $criteria, array $orderBy = null)
 * @method MessageContent[]    findAll()
 * @method MessageContent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageContentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageContent::class);
    }

    public function findOneByMessage(Message $message): ?MessageContent
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.message = :message')
            ->setParameter('message', $message)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return MessageContent[]
     */
    public function findByContentType(string $contentType): array
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.contentType = :contentType')
            ->setParameter('contentType', $contentType)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find content with text body
     * @return MessageContent[]
     */
    public function findWithTextBody(): array
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.bodyText IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find content with HTML body
     * @return MessageContent[]
     */
    public function findWithHtmlBody(): array
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.bodyHtml IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find content with structured data
     * @return MessageContent[]
     */
    public function findWithStructuredData(): array
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.structuredData IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find content with raw content
     * @return MessageContent[]
     */
    public function findWithRawContent(): array
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.rawContent IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search in text body content
     * @return MessageContent[]
     */
    public function searchInTextBody(string $searchTerm): array
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.bodyText LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search in HTML body content
     * @return MessageContent[]
     */
    public function searchInHtmlBody(string $searchTerm): array
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.bodyHtml LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search in both text and HTML body content
     * @return MessageContent[]
     */
    public function searchInBodies(string $searchTerm): array
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.bodyText LIKE :search OR mc.bodyHtml LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search in raw content
     * @return MessageContent[]
     */
    public function searchInRawContent(string $searchTerm): array
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.rawContent LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find content by content type pattern
     * @return MessageContent[]
     */
    public function findByContentTypePattern(string $pattern): array
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.contentType LIKE :pattern')
            ->setParameter('pattern', $pattern)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find multipart content (both text and HTML)
     * @return MessageContent[]
     */
    public function findMultipart(): array
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.bodyText IS NOT NULL')
            ->andWhere('mc.bodyHtml IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find plain text only content
     * @return MessageContent[]
     */
    public function findTextOnly(): array
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.bodyText IS NOT NULL')
            ->andWhere('mc.bodyHtml IS NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find HTML only content
     * @return MessageContent[]
     */
    public function findHtmlOnly(): array
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.bodyHtml IS NOT NULL')
            ->andWhere('mc.bodyText IS NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get content statistics by type
     * @return array<string, int>
     */
    public function getStatsByContentType(): array
    {
        $result = $this->createQueryBuilder('mc')
            ->select('mc.contentType, COUNT(mc.id) as count')
            ->groupBy('mc.contentType')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['contentType']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Count content by body type availability
     * @return array{textOnly: int, htmlOnly: int, multipart: int, empty: int}
     */
    public function getBodyTypeStats(): array
    {
        $textOnly = $this->createQueryBuilder('mc')
            ->select('COUNT(mc.id)')
            ->andWhere('mc.bodyText IS NOT NULL')
            ->andWhere('mc.bodyHtml IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $htmlOnly = $this->createQueryBuilder('mc')
            ->select('COUNT(mc.id)')
            ->andWhere('mc.bodyHtml IS NOT NULL')
            ->andWhere('mc.bodyText IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $multipart = $this->createQueryBuilder('mc')
            ->select('COUNT(mc.id)')
            ->andWhere('mc.bodyText IS NOT NULL')
            ->andWhere('mc.bodyHtml IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $empty = $this->createQueryBuilder('mc')
            ->select('COUNT(mc.id)')
            ->andWhere('mc.bodyText IS NULL')
            ->andWhere('mc.bodyHtml IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'textOnly' => (int) $textOnly,
            'htmlOnly' => (int) $htmlOnly,
            'multipart' => (int) $multipart,
            'empty' => (int) $empty
        ];
    }

    /**
     * Find orphaned content (messages that no longer exist)
     * @return MessageContent[]
     */
    public function findOrphaned(): array
    {
        return $this->createQueryBuilder('mc')
            ->leftJoin('mc.message', 'm')
            ->andWhere('m.id IS NULL')
            ->getQuery()
            ->getResult();
    }
}
