<?php

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;
use Nkamuo\NotificationTrackerBundle\Entity\ContactChannel;

/**
 * Repository for ContactChannel entities
 */
class ContactChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactChannel::class);
    }

    public function save(ContactChannel $channel, bool $flush = false): void
    {
        $this->getEntityManager()->persist($channel);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ContactChannel $channel, bool $flush = false): void
    {
        $this->getEntityManager()->remove($channel);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find channel by type and identifier
     */
    public function findByTypeAndIdentifier(string $type, string $identifier): ?ContactChannel
    {
        return $this->createQueryBuilder('ch')
            ->where('ch.type = :type')
            ->andWhere('ch.identifier = :identifier')
            ->setParameter('type', $type)
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find primary channels for a contact
     */
    public function findPrimaryChannels(Contact $contact): array
    {
        return $this->createQueryBuilder('ch')
            ->where('ch.contact = :contact')
            ->andWhere('ch.isPrimary = true')
            ->andWhere('ch.isActive = true')
            ->setParameter('contact', $contact)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active channels by type for a contact
     */
    public function findActiveChannelsByType(Contact $contact, string $type): array
    {
        return $this->createQueryBuilder('ch')
            ->where('ch.contact = :contact')
            ->andWhere('ch.type = :type')
            ->andWhere('ch.isActive = true')
            ->setParameter('contact', $contact)
            ->setParameter('type', $type)
            ->orderBy('ch.isPrimary', 'DESC')
            ->addOrderBy('ch.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find verified channels for a contact
     */
    public function findVerifiedChannels(Contact $contact): array
    {
        return $this->createQueryBuilder('ch')
            ->where('ch.contact = :contact')
            ->andWhere('ch.isVerified = true')
            ->andWhere('ch.isActive = true')
            ->setParameter('contact', $contact)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find channels needing verification
     */
    public function findPendingVerification(): array
    {
        return $this->createQueryBuilder('ch')
            ->where('ch.status = :pending')
            ->andWhere('ch.isActive = true')
            ->andWhere('ch.verificationTokenExpiresAt > :now OR ch.verificationTokenExpiresAt IS NULL')
            ->setParameter('pending', ContactChannel::STATUS_VERIFICATION_PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find expired verification tokens
     */
    public function findExpiredVerificationTokens(): array
    {
        return $this->createQueryBuilder('ch')
            ->where('ch.verificationTokenExpiresAt < :now')
            ->andWhere('ch.verificationToken IS NOT NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find channels with high bounce rates
     */
    public function findHighBounceRateChannels(float $threshold = 0.1): array
    {
        return $this->createQueryBuilder('ch')
            ->where('ch.bounceRate > :threshold')
            ->andWhere('ch.totalMessagesSent >= 10') // Minimum messages to be statistically relevant
            ->andWhere('ch.isActive = true')
            ->setParameter('threshold', $threshold)
            ->orderBy('ch.bounceRate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find channels with low delivery rates
     */
    public function findLowDeliveryRateChannels(float $threshold = 0.8): array
    {
        return $this->createQueryBuilder('ch')
            ->where('ch.deliveryRate < :threshold')
            ->andWhere('ch.totalMessagesSent >= 10')
            ->andWhere('ch.isActive = true')
            ->setParameter('threshold', $threshold)
            ->orderBy('ch.deliveryRate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get channel statistics by type
     */
    public function getStatisticsByType(): array
    {
        $result = $this->createQueryBuilder('ch')
            ->select([
                'ch.type',
                'COUNT(ch.id) as total',
                'SUM(CASE WHEN ch.isActive = true THEN 1 ELSE 0 END) as active',
                'SUM(CASE WHEN ch.isVerified = true THEN 1 ELSE 0 END) as verified',
                'AVG(ch.deliveryRate) as avgDeliveryRate',
                'AVG(ch.bounceRate) as avgBounceRate',
                'SUM(ch.totalMessagesSent) as totalMessagesSent',
                'SUM(ch.totalMessagesDelivered) as totalMessagesDelivered'
            ])
            ->groupBy('ch.type')
            ->getQuery()
            ->getResult();

        $statistics = [];
        foreach ($result as $row) {
            $statistics[$row['type']] = [
                'total' => (int) $row['total'],
                'active' => (int) $row['active'],
                'verified' => (int) $row['verified'],
                'averageDeliveryRate' => round((float) $row['avgDeliveryRate'], 4),
                'averageBounceRate' => round((float) $row['avgBounceRate'], 4),
                'totalMessagesSent' => (int) $row['totalMessagesSent'],
                'totalMessagesDelivered' => (int) $row['totalMessagesDelivered'],
            ];
        }

        return $statistics;
    }

    /**
     * Find channels that haven't been used recently
     */
    public function findUnusedChannels(\DateTimeInterface $before): array
    {
        return $this->createQueryBuilder('ch')
            ->where('ch.lastUsedAt < :before OR ch.lastUsedAt IS NULL')
            ->andWhere('ch.isActive = true')
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find duplicate channels (same type and identifier)
     */
    public function findDuplicateChannels(): array
    {
        $duplicates = $this->createQueryBuilder('ch')
            ->select('ch.type, ch.identifier, COUNT(ch.id) as count')
            ->groupBy('ch.type, ch.identifier')
            ->having('COUNT(ch.id) > 1')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($duplicates as $duplicate) {
            $channels = $this->createQueryBuilder('ch')
                ->where('ch.type = :type')
                ->andWhere('ch.identifier = :identifier')
                ->setParameter('type', $duplicate['type'])
                ->setParameter('identifier', $duplicate['identifier'])
                ->getQuery()
                ->getResult();
            
            if (count($channels) > 1) {
                $result[] = $channels;
            }
        }

        return $result;
    }

    /**
     * Update delivery statistics for a channel
     */
    public function updateDeliveryStats(ContactChannel $channel, bool $delivered, bool $bounced = false): void
    {
        $channel->updateDeliveryStats($delivered, $bounced);
        $this->save($channel, true);
    }

    /**
     * Cleanup expired verification tokens
     */
    public function cleanupExpiredTokens(): int
    {
        $expiredChannels = $this->findExpiredVerificationTokens();
        
        foreach ($expiredChannels as $channel) {
            $channel->setVerificationToken(null);
            $channel->setVerificationTokenExpiresAt(null);
            if ($channel->getVerificationAttempts() >= 3) {
                $channel->setStatus(ContactChannel::STATUS_VERIFICATION_FAILED);
            }
            $this->save($channel);
        }

        if (!empty($expiredChannels)) {
            $this->getEntityManager()->flush();
        }

        return count($expiredChannels);
    }

    /**
     * Find channels for contact deduplication
     */
    public function findChannelsForDeduplication(string $type, string $identifier): array
    {
        return $this->createQueryBuilder('ch')
            ->select('ch, c')
            ->innerJoin('ch.contact', 'c')
            ->where('ch.type = :type')
            ->andWhere('ch.identifier = :identifier')
            ->setParameter('type', $type)
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get channel performance metrics
     */
    public function getPerformanceMetrics(\DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('ch')
            ->select([
                'ch.type',
                'COUNT(ch.id) as channelCount',
                'SUM(ch.totalMessagesSent) as messagesSent',
                'SUM(ch.totalMessagesDelivered) as messagesDelivered',
                'SUM(ch.totalBounces) as bounces',
                'AVG(ch.deliveryRate) as avgDeliveryRate',
                'AVG(ch.bounceRate) as avgBounceRate'
            ])
            ->where('ch.lastUsedAt >= :since OR ch.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('ch.type')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find best performing channels for a contact
     */
    public function findBestPerformingChannels(Contact $contact, string $type = null): array
    {
        $qb = $this->createQueryBuilder('ch')
            ->where('ch.contact = :contact')
            ->andWhere('ch.isActive = true')
            ->andWhere('ch.totalMessagesSent > 0')
            ->setParameter('contact', $contact);

        if ($type) {
            $qb->andWhere('ch.type = :type')
               ->setParameter('type', $type);
        }

        return $qb->orderBy('ch.deliveryRate', 'DESC')
            ->addOrderBy('ch.bounceRate', 'ASC')
            ->addOrderBy('ch.isPrimary', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
