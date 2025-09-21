<?php

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;
use Nkamuo\NotificationTrackerBundle\Entity\ContactActivity;
use Nkamuo\NotificationTrackerBundle\Entity\ContactChannel;

/**
 * Repository for ContactActivity entities
 */
class ContactActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactActivity::class);
    }

    public function save(ContactActivity $activity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($activity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ContactActivity $activity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($activity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find activities for a contact
     */
    public function findByContact(Contact $contact, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.contact = :contact')
            ->setParameter('contact', $contact)
            ->orderBy('a.occurredAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find activities by type
     */
    public function findByType(string $type, int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.activityType = :type')
            ->setParameter('type', $type)
            ->orderBy('a.occurredAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find activities for a contact by type
     */
    public function findByContactAndType(Contact $contact, string $type, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.contact = :contact')
            ->andWhere('a.activityType = :type')
            ->setParameter('contact', $contact)
            ->setParameter('type', $type)
            ->orderBy('a.occurredAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find activities in date range
     */
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.occurredAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find activities for a contact in date range
     */
    public function findByContactAndDateRange(
        Contact $contact, 
        \DateTimeInterface $start, 
        \DateTimeInterface $end
    ): array {
        return $this->createQueryBuilder('a')
            ->where('a.contact = :contact')
            ->andWhere('a.occurredAt BETWEEN :start AND :end')
            ->setParameter('contact', $contact)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent activities for a contact
     */
    public function findRecentActivities(Contact $contact, \DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.contact = :contact')
            ->andWhere('a.occurredAt >= :since')
            ->setParameter('contact', $contact)
            ->setParameter('since', $since)
            ->orderBy('a.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find activities by channel
     */
    public function findByChannel(ContactChannel $channel, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.relatedChannelId = :channelId')
            ->setParameter('channelId', $channel->getId())
            ->orderBy('a.occurredAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get activity statistics for a contact
     */
    public function getContactActivityStats(Contact $contact, \DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select([
                'a.activityType',
                'COUNT(a.id) as count',
                'MAX(a.occurredAt) as lastActivity'
            ])
            ->where('a.contact = :contact')
            ->setParameter('contact', $contact);

        if ($since) {
            $qb->andWhere('a.occurredAt >= :since')
               ->setParameter('since', $since);
        }

        $result = $qb->groupBy('a.activityType')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['activityType']] = [
                'count' => (int) $row['count'],
                'lastActivity' => $row['lastActivity'],
            ];
        }

        return $stats;
    }

    /**
     * Get overall activity statistics
     */
    public function getActivityStatistics(\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select([
                'COUNT(a.id) as totalActivities',
                'COUNT(DISTINCT a.contact) as activeContacts',
                'a.type',
                'COUNT(a.id) as typeCount'
            ]);

        if ($since) {
            $qb->where('a.occurredAt >= :since')
               ->setParameter('since', $since);
        }

        // Get total counts
        $totalResult = $this->createQueryBuilder('a')
            ->select([
                'COUNT(a.id) as totalActivities',
                'COUNT(DISTINCT a.contact) as activeContacts'
            ]);

        if ($since) {
            $totalResult->where('a.occurredAt >= :since')
                        ->setParameter('since', $since);
        }

        $totals = $totalResult->getQuery()->getSingleResult();

        // Get counts by type
        $typeResult = $this->createQueryBuilder('a')
            ->select([
                'a.activityType',
                'COUNT(a.id) as count'
            ]);

        if ($since) {
            $typeResult->where('a.occurredAt >= :since')
                       ->setParameter('since', $since);
        }

        $typeStats = $typeResult->groupBy('a.activityType')
            ->getQuery()
            ->getResult();

        $stats = [
            'totalActivities' => (int) $totals['totalActivities'],
            'activeContacts' => (int) $totals['activeContacts'],
            'byType' => []
        ];

        foreach ($typeStats as $row) {
            $stats['byType'][$row['activityType']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Find most active contacts
     */
    public function findMostActiveContacts(\DateTimeInterface $since, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->select('IDENTITY(a.contact) as contactId, COUNT(a.id) as activityCount')
            ->where('a.occurredAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('a.contact')
            ->orderBy('activityCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get activity trend data
     */
    public function getActivityTrend(\DateTimeInterface $start, \DateTimeInterface $end, string $interval = 'day'): array
    {
        $dateFormat = match($interval) {
            'hour' => 'Y-m-d H:00:00',
            'day' => 'Y-m-d',
            'week' => 'Y-W',
            'month' => 'Y-m',
            default => 'Y-m-d'
        };

        return $this->createQueryBuilder('a')
            ->select([
                "DATE_FORMAT(a.occurredAt, '{$dateFormat}') as period",
                'COUNT(a.id) as count'
            ])
            ->where('a.occurredAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('period')
            ->orderBy('period', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Clean up old activities
     */
    public function cleanupOldActivities(\DateTimeInterface $before, array $typesToKeep = []): int
    {
        $qb = $this->createQueryBuilder('a')
            ->delete()
            ->where('a.occurredAt < :before')
            ->setParameter('before', $before);

        if (!empty($typesToKeep)) {
            $qb->andWhere('a.type NOT IN (:typesToKeep)')
               ->setParameter('typesToKeep', $typesToKeep);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * Record a new activity
     */
    public function recordActivity(
        Contact $contact,
        string $type,
        array $metadata = [],
        ContactChannel $channel = null,
        \DateTimeInterface $occurredAt = null
    ): ContactActivity {
        $activity = new ContactActivity();
        $activity->setContact($contact);
        $activity->setActivityType($type);
        $activity->setMetadataArray($metadata);
        
        if ($channel) {
            $activity->setRelatedChannelId($channel->getId());
        }
        
        if ($occurredAt) {
            $activity->setOccurredAt($occurredAt);
        }

        $this->save($activity, true);

        return $activity;
    }

    /**
     * Find engagement activities for a contact
     */
    public function findEngagementActivities(Contact $contact, \DateTimeInterface $since = null): array
    {
        $engagementTypes = [
            ContactActivity::TYPE_MESSAGE_OPENED,
            ContactActivity::TYPE_MESSAGE_CLICKED,
            ContactActivity::TYPE_OPTED_IN,
            ContactActivity::TYPE_OPTED_OUT,
            ContactActivity::TYPE_PREFERENCE_UPDATED
        ];

        $qb = $this->createQueryBuilder('a')
            ->where('a.contact = :contact')
            ->andWhere('a.activityType IN (:types)')
            ->setParameter('contact', $contact)
            ->setParameter('types', $engagementTypes);

        if ($since) {
            $qb->andWhere('a.occurredAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->orderBy('a.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get last activity for each contact
     */
    public function getLastActivityForContacts(array $contactIds): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.contact) as contactId, MAX(a.occurredAt) as lastActivity')
            ->where('a.contact IN (:contactIds)')
            ->setParameter('contactIds', $contactIds)
            ->groupBy('a.contact')
            ->getQuery()
            ->getResult();

        $lastActivities = [];
        foreach ($result as $row) {
            $lastActivities[$row['contactId']] = $row['lastActivity'];
        }

        return $lastActivities;
    }

    /**
     * Find contacts with no recent activity
     */
    public function findInactiveContacts(\DateTimeInterface $before): array
    {
        $activeContactIds = $this->createQueryBuilder('a')
            ->select('DISTINCT IDENTITY(a.contact)')
            ->where('a.occurredAt >= :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getSingleColumnResult();

        // Get all contacts excluding those with recent activity
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c')
            ->from(Contact::class, 'c')
            ->where('c.isActive = true');

        if (!empty($activeContactIds)) {
            $qb->andWhere('c.id NOT IN (:activeIds)')
               ->setParameter('activeIds', $activeContactIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get activity summary for dashboard
     */
    public function getActivitySummary(\DateTimeInterface $since): array
    {
        $stats = $this->getActivityStatistics($since);
        $trend = $this->getActivityTrend($since, new \DateTimeImmutable(), 'day');
        $mostActive = $this->findMostActiveContacts($since, 5);

        return [
            'statistics' => $stats,
            'trend' => $trend,
            'mostActiveContacts' => $mostActive
        ];
    }

    /**
     * Bulk insert activities for performance
     */
    public function bulkInsertActivities(array $activities): void
    {
        $batchSize = 100;
        $count = 0;

        foreach ($activities as $activityData) {
            $activity = new ContactActivity();
            $activity->setContact($activityData['contact']);
            $activity->setActivityType($activityData['type']);
            $activity->setMetadataArray($activityData['metadata'] ?? []);
            
            if (isset($activityData['channel'])) {
                $activity->setRelatedChannelId($activityData['channel']->getId());
            }
            
            if (isset($activityData['occurredAt'])) {
                $activity->setOccurredAt($activityData['occurredAt']);
            }

            $this->getEntityManager()->persist($activity);

            if (++$count % $batchSize === 0) {
                $this->getEntityManager()->flush();
                $this->getEntityManager()->clear();
            }
        }

        if ($count % $batchSize !== 0) {
            $this->getEntityManager()->flush();
            $this->getEntityManager()->clear();
        }
    }
}
