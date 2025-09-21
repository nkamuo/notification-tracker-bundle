<?php

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;
use Nkamuo\NotificationTrackerBundle\Entity\ContactChannel;
use Nkamuo\NotificationTrackerBundle\Entity\ContactChannelPreference;

/**
 * Repository for ContactChannelPreference entities
 */
class ContactChannelPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactChannelPreference::class);
    }

    public function save(ContactChannelPreference $preference, bool $flush = false): void
    {
        $this->getEntityManager()->persist($preference);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ContactChannelPreference $preference, bool $flush = false): void
    {
        $this->getEntityManager()->remove($preference);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find preference by channel and category
     */
    public function findByChannelAndCategory(ContactChannel $channel, ?string $category = null): ?ContactChannelPreference
    {
        $qb = $this->createQueryBuilder('pref')
            ->where('pref.contactChannel = :channel')
            ->setParameter('channel', $channel);

        if ($category) {
            $qb->andWhere('pref.category = :category')
               ->setParameter('category', $category);
        } else {
            $qb->andWhere('pref.category IS NULL');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Find all preferences for a channel
     */
    public function findByChannel(ContactChannel $channel): array
    {
        return $this->createQueryBuilder('pref')
            ->where('pref.contactChannel = :channel')
            ->setParameter('channel', $channel)
            ->orderBy('pref.category', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all preferences for a contact
     */
    public function findByContact(Contact $contact): array
    {
        return $this->createQueryBuilder('pref')
            ->innerJoin('pref.contactChannel', 'ch')
            ->where('ch.contact = :contact')
            ->setParameter('contact', $contact)
            ->orderBy('ch.type', 'ASC')
            ->addOrderBy('pref.category', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find opted-out preferences for a contact
     */
    public function findOptedOutPreferences(Contact $contact): array
    {
        return $this->createQueryBuilder('pref')
            ->innerJoin('pref.contactChannel', 'ch')
            ->where('ch.contact = :contact')
            ->andWhere('pref.isOptedIn = false')
            ->setParameter('contact', $contact)
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if messages are allowed for a specific channel and category
     */
    public function isMessagesAllowed(ContactChannel $channel, string $category = null, string $senderId = null): bool
    {
        $preference = $this->findByChannelAndCategory($channel, $category);
        
        if (!$preference) {
            // No specific preference, check global preference
            $globalPreference = $this->findByChannelAndCategory($channel, null);
            if (!$globalPreference) {
                // No preferences set, default to allowed
                return true;
            }
            $preference = $globalPreference;
        }

        return $preference->isMessagesAllowed($senderId);
    }

    /**
     * Check if message frequency is within limits
     */
    public function isWithinFrequencyLimits(ContactChannel $channel, string $category = null): bool
    {
        $preference = $this->findByChannelAndCategory($channel, $category);
        
        if (!$preference) {
            return true; // No limits set
        }

        return $preference->isWithinFrequencyLimits();
    }

    /**
     * Find preferences with custom rules
     */
    public function findWithCustomRules(): array
    {
        return $this->createQueryBuilder('pref')
            ->where('pref.customRules IS NOT NULL')
            ->andWhere('pref.customRules != :empty')
            ->setParameter('empty', '[]')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find preferences with quiet hours configured
     */
    public function findWithQuietHours(): array
    {
        return $this->createQueryBuilder('pref')
            ->where('pref.quietHoursStart IS NOT NULL')
            ->andWhere('pref.quietHoursEnd IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find preferences that need rate limit reset
     */
    public function findNeedingRateLimitReset(): array
    {
        return $this->createQueryBuilder('pref')
            ->where('pref.rateLimitWindowStart < :windowStart')
            ->andWhere('pref.rateLimitWindowStart IS NOT NULL')
            ->setParameter('windowStart', new \DateTimeImmutable('-1 day'))
            ->getQuery()
            ->getResult();
    }

    /**
     * Get preference statistics
     */
    public function getPreferenceStatistics(): array
    {
        $result = $this->createQueryBuilder('pref')
            ->select([
                'COUNT(pref.id) as total',
                'SUM(CASE WHEN pref.isOptedIn = true THEN 1 ELSE 0 END) as optedIn',
                'SUM(CASE WHEN pref.isOptedIn = false THEN 1 ELSE 0 END) as optedOut',
                'SUM(CASE WHEN pref.quietHoursStart IS NOT NULL THEN 1 ELSE 0 END) as withQuietHours',
                'SUM(CASE WHEN pref.maxMessagesPerDay > 0 THEN 1 ELSE 0 END) as withDailyLimits',
                'SUM(CASE WHEN pref.maxMessagesPerWeek > 0 THEN 1 ELSE 0 END) as withWeeklyLimits',
                'SUM(CASE WHEN pref.customRules IS NOT NULL AND pref.customRules != :empty THEN 1 ELSE 0 END) as withCustomRules'
            ])
            ->setParameter('empty', '[]')
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'optedIn' => (int) $result['optedIn'],
            'optedOut' => (int) $result['optedOut'],
            'withQuietHours' => (int) $result['withQuietHours'],
            'withDailyLimits' => (int) $result['withDailyLimits'],
            'withWeeklyLimits' => (int) $result['withWeeklyLimits'],
            'withCustomRules' => (int) $result['withCustomRules'],
        ];
    }

    /**
     * Get preference statistics by category
     */
    public function getStatisticsByCategory(): array
    {
        $result = $this->createQueryBuilder('pref')
            ->select([
                'pref.category',
                'COUNT(pref.id) as total',
                'SUM(CASE WHEN pref.isOptedIn = true THEN 1 ELSE 0 END) as optedIn',
                'SUM(CASE WHEN pref.isOptedIn = false THEN 1 ELSE 0 END) as optedOut'
            ])
            ->groupBy('pref.category')
            ->getQuery()
            ->getResult();

        $statistics = [];
        foreach ($result as $row) {
            $category = $row['category'] ?? 'general';
            $statistics[$category] = [
                'total' => (int) $row['total'],
                'optedIn' => (int) $row['optedIn'],
                'optedOut' => (int) $row['optedOut'],
                'optInRate' => $row['total'] > 0 ? round($row['optedIn'] / $row['total'], 4) : 0,
            ];
        }

        return $statistics;
    }

    /**
     * Find preferences by channel type
     */
    public function findByChannelType(string $channelType): array
    {
        return $this->createQueryBuilder('pref')
            ->innerJoin('pref.contactChannel', 'ch')
            ->where('ch.type = :type')
            ->setParameter('type', $channelType)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find preferences that allow a specific category
     */
    public function findAllowingCategory(string $category): array
    {
        return $this->createQueryBuilder('pref')
            ->where('pref.category = :category')
            ->andWhere('pref.isOptedIn = true')
            ->setParameter('category', $category)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find preferences with sender restrictions
     */
    public function findWithSenderRestrictions(): array
    {
        return $this->createQueryBuilder('pref')
            ->where('pref.allowedSenders IS NOT NULL AND pref.allowedSenders != :empty')
            ->orWhere('pref.blockedSenders IS NOT NULL AND pref.blockedSenders != :empty')
            ->setParameter('empty', '[]')
            ->getQuery()
            ->getResult();
    }

    /**
     * Reset rate limits for preferences
     */
    public function resetRateLimits(): int
    {
        $preferences = $this->findNeedingRateLimitReset();
        
        foreach ($preferences as $preference) {
            $preference->resetRateLimit();
            $this->save($preference);
        }

        if (!empty($preferences)) {
            $this->getEntityManager()->flush();
        }

        return count($preferences);
    }

    /**
     * Record message sent for rate limiting
     */
    public function recordMessageSent(ContactChannel $channel, string $category = null): void
    {
        $preference = $this->findByChannelAndCategory($channel, $category);
        
        if (!$preference) {
            // Create default preference if none exists
            $preference = new ContactChannelPreference();
            $preference->setContactChannel($channel);
            if ($category) {
                // This would need to be implemented in the entity
                // For now, we'll skip setting category
            }
            $preference->setAllowNotifications(true);
        }

        // Record the message (implementation would depend on actual tracking fields)
        $this->save($preference, true);
    }

    /**
     * Bulk update opt-in status for a contact
     */
    public function bulkUpdateOptInStatus(Contact $contact, bool $isOptedIn, array $categories = null): int
    {
        $qb = $this->createQueryBuilder('pref')
            ->update()
            ->set('pref.isOptedIn', ':optedIn')
            ->set('pref.updatedAt', ':now')
            ->innerJoin('pref.contactChannel', 'ch')
            ->where('ch.contact = :contact')
            ->setParameter('optedIn', $isOptedIn)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('contact', $contact);

        if ($categories) {
            $qb->andWhere('pref.category IN (:categories)')
               ->setParameter('categories', $categories);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * Find preferences approaching rate limits
     */
    public function findApproachingRateLimits(float $threshold = 0.8): array
    {
        return $this->createQueryBuilder('pref')
            ->where('(pref.maxMessagesPerDay > 0 AND pref.messagesThisDay >= :dailyThreshold * pref.maxMessagesPerDay)')
            ->orWhere('(pref.maxMessagesPerWeek > 0 AND pref.messagesThisWeek >= :weeklyThreshold * pref.maxMessagesPerWeek)')
            ->setParameter('dailyThreshold', $threshold)
            ->setParameter('weeklyThreshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * Clean up old preference history
     */
    public function cleanupOldHistory(\DateTimeInterface $before): int
    {
        // This would require additional tracking of historical data
        // For now, we'll just mark it as a placeholder for future implementation
        return 0;
    }

    /**
     * Find conflicting preferences (e.g., opted out but with sender allowlist)
     */
    public function findConflictingPreferences(): array
    {
        return $this->createQueryBuilder('pref')
            ->where('pref.isOptedIn = false')
            ->andWhere('pref.allowedSenders IS NOT NULL AND pref.allowedSenders != :empty')
            ->setParameter('empty', '[]')
            ->getQuery()
            ->getResult();
    }
}
