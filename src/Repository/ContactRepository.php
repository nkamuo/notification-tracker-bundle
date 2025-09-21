<?php

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;
use Nkamuo\NotificationTrackerBundle\Entity\ContactChannel;

/**
 * Repository for Contact entities with advanced search and analytics capabilities
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    public function save(Contact $contact, bool $flush = false): void
    {
        $this->getEntityManager()->persist($contact);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Contact $contact, bool $flush = false): void
    {
        $this->getEntityManager()->remove($contact);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find contact by email address across all their channels
     */
    public function findByEmail(string $email): ?Contact
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.channels', 'ch')
            ->where('ch.type = :email_type')
            ->andWhere('ch.identifier = :email')
            ->andWhere('ch.isActive = true')
            ->setParameter('email_type', ContactChannel::TYPE_EMAIL)
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find contact by phone number across all their channels
     */
    public function findByPhone(string $phone): ?Contact
    {
        // Normalize phone number for search
        $normalizedPhone = preg_replace('/[^\d+]/', '', $phone);
        
        return $this->createQueryBuilder('c')
            ->innerJoin('c.channels', 'ch')
            ->where('ch.type IN (:phone_types)')
            ->andWhere('REGEXP(ch.identifier, :phone_pattern) = true')
            ->andWhere('ch.isActive = true')
            ->setParameter('phone_types', [ContactChannel::TYPE_SMS, ContactChannel::TYPE_VOICE])
            ->setParameter('phone_pattern', $normalizedPhone)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find contact by any channel identifier
     */
    public function findByChannelIdentifier(string $type, string $identifier): ?Contact
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.channels', 'ch')
            ->where('ch.type = :type')
            ->andWhere('ch.identifier = :identifier')
            ->andWhere('ch.isActive = true')
            ->setParameter('type', $type)
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find contacts with specific tags
     */
    public function findByTags(array $tags, bool $requireAll = false): array
    {
        $qb = $this->createQueryBuilder('c');
        
        if ($requireAll) {
            // Contact must have ALL specified tags
            foreach ($tags as $index => $tag) {
                $qb->andWhere("JSON_CONTAINS(c.tags, :tag_{$index}) = 1")
                   ->setParameter("tag_{$index}", json_encode($tag));
            }
        } else {
            // Contact must have ANY of the specified tags
            $conditions = [];
            foreach ($tags as $index => $tag) {
                $conditions[] = "JSON_CONTAINS(c.tags, :tag_{$index}) = 1";
                $qb->setParameter("tag_{$index}", json_encode($tag));
            }
            $qb->where(implode(' OR ', $conditions));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Search contacts by name, email, or other identifiers
     */
    public function search(string $query, int $limit = 20): array
    {
        $searchQuery = '%' . strtolower($query) . '%';

        return $this->createQueryBuilder('c')
            ->leftJoin('c.channels', 'ch')
            ->where('LOWER(c.firstName) LIKE :query')
            ->orWhere('LOWER(c.lastName) LIKE :query')
            ->orWhere('LOWER(c.displayName) LIKE :query')
            ->orWhere('LOWER(c.organizationName) LIKE :query')
            ->orWhere('LOWER(ch.identifier) LIKE :query')
            ->setParameter('query', $searchQuery)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find contacts by engagement score range
     */
    public function findByEngagementScore(int $minScore, int $maxScore = 100): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.engagementScore >= :min_score')
            ->andWhere('c.engagementScore <= :max_score')
            ->andWhere('c.status = :active')
            ->setParameter('min_score', $minScore)
            ->setParameter('max_score', $maxScore)
            ->setParameter('active', Contact::STATUS_ACTIVE)
            ->orderBy('c.engagementScore', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently active contacts
     */
    public function findRecentlyActive(\DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.lastEngagedAt >= :since')
            ->andWhere('c.status = :active')
            ->setParameter('since', $since)
            ->setParameter('active', Contact::STATUS_ACTIVE)
            ->orderBy('c.lastEngagedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find inactive contacts (no recent engagement)
     */
    public function findInactive(\DateTimeInterface $before): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.lastEngagedAt < :before OR c.lastEngagedAt IS NULL')
            ->andWhere('c.status = :active')
            ->setParameter('before', $before)
            ->setParameter('active', Contact::STATUS_ACTIVE)
            ->orderBy('c.lastEngagedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find contacts with specific channel types
     */
    public function findWithChannelType(string $channelType, bool $verifiedOnly = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.channels', 'ch')
            ->where('ch.type = :channel_type')
            ->andWhere('ch.isActive = true')
            ->setParameter('channel_type', $channelType);

        if ($verifiedOnly) {
            $qb->andWhere('ch.isVerified = true');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get contacts statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('c');
        
        $result = $qb->select([
                'COUNT(c.id) as total',
                'SUM(CASE WHEN c.status = :active THEN 1 ELSE 0 END) as active',
                'SUM(CASE WHEN c.status = :inactive THEN 1 ELSE 0 END) as inactive',
                'SUM(CASE WHEN c.status = :blocked THEN 1 ELSE 0 END) as blocked',
                'AVG(c.engagementScore) as avgEngagementScore',
                'COUNT(CASE WHEN c.verifiedAt IS NOT NULL THEN 1 END) as verified'
            ])
            ->setParameter('active', Contact::STATUS_ACTIVE)
            ->setParameter('inactive', Contact::STATUS_INACTIVE)
            ->setParameter('blocked', Contact::STATUS_BLOCKED)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'total' => (int) $result['total'],
            'active' => (int) $result['active'],
            'inactive' => (int) $result['inactive'],
            'blocked' => (int) $result['blocked'],
            'verified' => (int) $result['verified'],
            'averageEngagementScore' => round((float) $result['avgEngagementScore'], 2),
        ];
    }

    /**
     * Get contact engagement analytics
     */
    public function getEngagementAnalytics(\DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('c')
            ->select([
                'COUNT(c.id) as totalContacts',
                'SUM(c.totalMessagesSent) as totalMessagesSent',
                'SUM(c.totalMessagesDelivered) as totalMessagesDelivered',
                'SUM(c.totalMessagesOpened) as totalMessagesOpened',
                'SUM(c.totalMessagesClicked) as totalMessagesClicked',
                'AVG(c.engagementScore) as averageEngagementScore',
                'COUNT(CASE WHEN c.lastEngagedAt >= :since THEN 1 END) as recentlyEngaged'
            ])
            ->where('c.status = :active')
            ->setParameter('active', Contact::STATUS_ACTIVE)
            ->setParameter('since', $since)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find potential duplicate contacts based on email similarity
     */
    public function findPotentialDuplicates(Contact $contact): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.id != :contact_id')
            ->setParameter('contact_id', $contact->getId());

        $conditions = [];

        // Check by email hash
        if ($contact->getEmailHash()) {
            $conditions[] = 'c.emailHash = :email_hash';
            $qb->setParameter('email_hash', $contact->getEmailHash());
        }

        // Check by name similarity
        if ($contact->getFirstName() && $contact->getLastName()) {
            $conditions[] = '(c.firstName = :first_name AND c.lastName = :last_name)';
            $qb->setParameter('first_name', $contact->getFirstName())
               ->setParameter('last_name', $contact->getLastName());
        }

        if (empty($conditions)) {
            return [];
        }

        $qb->andWhere('(' . implode(' OR ', $conditions) . ')');

        return $qb->getQuery()->getResult();
    }

    /**
     * Get contacts created within a date range
     */
    public function findCreatedBetween(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.createdAt >= :start')
            ->andWhere('c.createdAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Create query builder for advanced filtering
     */
    public function createAdvancedSearchQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.channels', 'ch')
            ->leftJoin('c.groups', 'g')
            ->leftJoin('c.activities', 'a');
    }

    /**
     * Bulk update engagement scores
     */
    public function updateEngagementScores(array $contactIds): int
    {
        // This would typically be done in a batch job
        $updated = 0;
        $em = $this->getEntityManager();
        
        foreach (array_chunk($contactIds, 100) as $chunk) {
            $contacts = $this->findBy(['id' => $chunk]);
            foreach ($contacts as $contact) {
                $contact->updateEngagementScore();
                $em->persist($contact);
                $updated++;
            }
            $em->flush();
            $em->clear();
        }
        
        return $updated;
    }

    /**
     * Find contacts for cleanup (merged, inactive, etc.)
     */
    public function findForCleanup(\DateTimeInterface $before): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :merged OR (c.status = :inactive AND c.updatedAt < :before)')
            ->setParameter('merged', Contact::STATUS_MERGED)
            ->setParameter('inactive', Contact::STATUS_INACTIVE)
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }
}
