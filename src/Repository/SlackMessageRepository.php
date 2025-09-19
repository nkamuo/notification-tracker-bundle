<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\SlackMessage;

/**
 * @extends ServiceEntityRepository<SlackMessage>
 * @method SlackMessage|null find($id, $lockMode = null, $lockVersion = null)
 * @method SlackMessage|null findOneBy(array $criteria, array $orderBy = null)
 * @method SlackMessage[]    findAll()
 * @method SlackMessage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SlackMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SlackMessage::class);
    }

    /**
     * @return SlackMessage[]
     */
    public function findByChannel(string $channel): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.channel = :channel')
            ->setParameter('channel', $channel)
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SlackMessage[]
     */
    public function findByThreadTs(string $threadTs): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.threadTs = :threadTs')
            ->setParameter('threadTs', $threadTs)
            ->orderBy('sm.sentAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SlackMessage[]
     */
    public function findThreadMessages(): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.threadTs IS NOT NULL')
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SlackMessage[]
     */
    public function findDirectMessages(): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.threadTs IS NULL')
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SlackMessage[]
     */
    public function findWithBlocks(): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.blocks IS NOT NULL')
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SlackMessage[]
     */
    public function findWithAttachments(): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.attachments IS NOT NULL')
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SlackMessage[]
     */
    public function findByChannelPattern(string $pattern): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.channel LIKE :pattern')
            ->setParameter('pattern', $pattern)
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search in message content (blocks and attachments)
     * @return SlackMessage[]
     */
    public function searchInContent(string $searchTerm): array
    {
        return $this->createQueryBuilder('sm')
            ->leftJoin('sm.content', 'mc')
            ->andWhere('mc.bodyText LIKE :search OR JSON_EXTRACT(sm.blocks, \'$\') LIKE :search OR JSON_EXTRACT(sm.attachments, \'$\') LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find messages with specific block type
     * @return SlackMessage[]
     */
    public function findByBlockType(string $blockType): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('JSON_SEARCH(sm.blocks, \'one\', :blockType, NULL, \'$[*].type\') IS NOT NULL')
            ->setParameter('blockType', $blockType)
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get statistics by channel
     * @return array<string, int>
     */
    public function getStatsByChannel(): array
    {
        $result = $this->createQueryBuilder('sm')
            ->select('sm.channel, COUNT(sm.id) as count')
            ->groupBy('sm.channel')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['channel']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get thread statistics
     * @return array{threaded: int, direct: int}
     */
    public function getThreadStats(): array
    {
        $threaded = $this->createQueryBuilder('sm')
            ->select('COUNT(sm.id)')
            ->andWhere('sm.threadTs IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $direct = $this->createQueryBuilder('sm')
            ->select('COUNT(sm.id)')
            ->andWhere('sm.threadTs IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'threaded' => (int) $threaded,
            'direct' => (int) $direct
        ];
    }

    /**
     * Get content type statistics
     * @return array{withBlocks: int, withAttachments: int, textOnly: int}
     */
    public function getContentTypeStats(): array
    {
        $withBlocks = $this->createQueryBuilder('sm')
            ->select('COUNT(sm.id)')
            ->andWhere('sm.blocks IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $withAttachments = $this->createQueryBuilder('sm')
            ->select('COUNT(sm.id)')
            ->andWhere('sm.attachments IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $textOnly = $this->createQueryBuilder('sm')
            ->select('COUNT(sm.id)')
            ->andWhere('sm.blocks IS NULL')
            ->andWhere('sm.attachments IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'withBlocks' => (int) $withBlocks,
            'withAttachments' => (int) $withAttachments,
            'textOnly' => (int) $textOnly
        ];
    }

    /**
     * Find most active channels
     * @return array<string, int>
     */
    public function getMostActiveChannels(int $limit = 10): array
    {
        $result = $this->createQueryBuilder('sm')
            ->select('sm.channel, COUNT(sm.id) as count')
            ->groupBy('sm.channel')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['channel']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Find messages in channel by date range
     * @return SlackMessage[]
     */
    public function findByChannelAndDateRange(string $channel, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.channel = :channel')
            ->andWhere('sm.sentAt >= :from')
            ->andWhere('sm.sentAt <= :to')
            ->setParameter('channel', $channel)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('sm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find conversation thread by thread timestamp
     * @return SlackMessage[]
     */
    public function findConversationThread(string $threadTs): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.threadTs = :threadTs')
            ->setParameter('threadTs', $threadTs)
            ->orderBy('sm.sentAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count messages per channel in date range
     * @return array<string, int>
     */
    public function getChannelActivityInDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $result = $this->createQueryBuilder('sm')
            ->select('sm.channel, COUNT(sm.id) as count')
            ->andWhere('sm.sentAt >= :from')
            ->andWhere('sm.sentAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('sm.channel')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['channel']] = (int) $row['count'];
        }

        return $stats;
    }
}
