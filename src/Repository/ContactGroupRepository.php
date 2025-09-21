<?php

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;
use Nkamuo\NotificationTrackerBundle\Entity\ContactGroup;

/**
 * Repository for ContactGroup entities
 */
class ContactGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactGroup::class);
    }

    public function save(ContactGroup $group, bool $flush = false): void
    {
        $this->getEntityManager()->persist($group);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ContactGroup $group, bool $flush = false): void
    {
        $this->getEntityManager()->remove($group);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find groups by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.type = :type')
            ->andWhere('g.isActive = true')
            ->setParameter('type', $type)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find static groups
     */
    public function findStaticGroups(): array
    {
        return $this->findByType(ContactGroup::TYPE_STATIC);
    }

    /**
     * Find dynamic groups
     */
    public function findDynamicGroups(): array
    {
        return $this->findByType(ContactGroup::TYPE_DYNAMIC);
    }

    /**
     * Find behavior-based groups
     */
    public function findBehaviorGroups(): array
    {
        return $this->findByType(ContactGroup::TYPE_BEHAVIOR);
    }

    /**
     * Find groups that a contact belongs to
     */
    public function findGroupsForContact(Contact $contact): array
    {
        return $this->createQueryBuilder('g')
            ->innerJoin('g.contacts', 'c')
            ->where('c.id = :contactId')
            ->andWhere('g.isActive = true')
            ->setParameter('contactId', $contact->getId())
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find groups by tags
     */
    public function findByTags(array $tags): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.tags && :tags')
            ->andWhere('g.isActive = true')
            ->setParameter('tags', $tags)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find child groups of a parent
     */
    public function findChildGroups(ContactGroup $parent): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.parentGroup = :parent')
            ->andWhere('g.isActive = true')
            ->setParameter('parent', $parent)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find root groups (no parent)
     */
    public function findRootGroups(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.parentGroup IS NULL')
            ->andWhere('g.isActive = true')
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find groups with dynamic criteria that need evaluation
     */
    public function findGroupsNeedingEvaluation(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.type IN (:dynamicTypes)')
            ->andWhere('g.isActive = true')
            ->andWhere('g.criteria IS NOT NULL')
            ->andWhere('g.criteria != :empty')
            ->setParameter('dynamicTypes', [ContactGroup::TYPE_DYNAMIC, ContactGroup::TYPE_BEHAVIOR])
            ->setParameter('empty', '[]')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find groups by contact count range
     */
    public function findByContactCountRange(int $min, int $max = null): array
    {
        $qb = $this->createQueryBuilder('g')
            ->where('g.contactCount >= :min')
            ->andWhere('g.isActive = true')
            ->setParameter('min', $min);

        if ($max !== null) {
            $qb->andWhere('g.contactCount <= :max')
               ->setParameter('max', $max);
        }

        return $qb->orderBy('g.contactCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find empty groups
     */
    public function findEmptyGroups(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.contactCount = 0')
            ->andWhere('g.isActive = true')
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find largest groups
     */
    public function findLargestGroups(int $limit = 10): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.isActive = true')
            ->orderBy('g.contactCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get group statistics
     */
    public function getGroupStatistics(): array
    {
        $result = $this->createQueryBuilder('g')
            ->select([
                'COUNT(g.id) as totalGroups',
                'SUM(CASE WHEN g.type = :static THEN 1 ELSE 0 END) as staticGroups',
                'SUM(CASE WHEN g.type = :dynamic THEN 1 ELSE 0 END) as dynamicGroups',
                'SUM(CASE WHEN g.type = :behavior THEN 1 ELSE 0 END) as behaviorGroups',
                'SUM(g.contactCount) as totalMemberships',
                'AVG(g.contactCount) as averageSize',
                'MAX(g.contactCount) as largestGroup',
                'SUM(CASE WHEN g.contactCount = 0 THEN 1 ELSE 0 END) as emptyGroups'
            ])
            ->where('g.isActive = true')
            ->setParameter('static', ContactGroup::TYPE_STATIC)
            ->setParameter('dynamic', ContactGroup::TYPE_DYNAMIC)
            ->setParameter('behavior', ContactGroup::TYPE_BEHAVIOR)
            ->getQuery()
            ->getSingleResult();

        return [
            'totalGroups' => (int) $result['totalGroups'],
            'staticGroups' => (int) $result['staticGroups'],
            'dynamicGroups' => (int) $result['dynamicGroups'],
            'behaviorGroups' => (int) $result['behaviorGroups'],
            'totalMemberships' => (int) $result['totalMemberships'],
            'averageSize' => round((float) $result['averageSize'], 2),
            'largestGroup' => (int) $result['largestGroup'],
            'emptyGroups' => (int) $result['emptyGroups'],
        ];
    }

    /**
     * Search groups by name or description
     */
    public function searchGroups(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('g')
            ->where('LOWER(g.name) LIKE LOWER(:query)')
            ->orWhere('LOWER(g.description) LIKE LOWER(:query)')
            ->andWhere('g.isActive = true')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('g.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Update contact count for a group
     */
    public function updateContactCount(ContactGroup $group): void
    {
        $count = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Contact::class, 'c')
            ->innerJoin('c.groups', 'g')
            ->where('g.id = :groupId')
            ->setParameter('groupId', $group->getId())
            ->getQuery()
            ->getSingleScalarResult();

        $group->setContactCount((int) $count);
        $this->save($group, true);
    }

    /**
     * Bulk update contact counts for all groups
     */
    public function updateAllContactCounts(): int
    {
        $groups = $this->findAll();
        $updated = 0;

        foreach ($groups as $group) {
            $oldCount = $group->getContactCount();
            $this->updateContactCount($group);
            
            if ($oldCount !== $group->getContactCount()) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Find groups that contacts qualify for based on criteria
     */
    public function findQualifyingGroups(Contact $contact): array
    {
        $dynamicGroups = $this->findGroupsNeedingEvaluation();
        $qualifyingGroups = [];

        foreach ($dynamicGroups as $group) {
            if ($this->contactQualifiesForGroup($contact, $group)) {
                $qualifyingGroups[] = $group;
            }
        }

        return $qualifyingGroups;
    }

    /**
     * Check if a contact qualifies for a group based on criteria
     */
    public function contactQualifiesForGroup(Contact $contact, ContactGroup $group): bool
    {
        $criteria = $group->getCriteria();
        
        if (empty($criteria)) {
            return false;
        }

        // This is a simplified criteria evaluator
        // In a real implementation, you might want to use a more sophisticated rule engine
        return $this->evaluateCriteria($contact, $criteria);
    }

    /**
     * Evaluate criteria for a contact
     */
    private function evaluateCriteria(Contact $contact, array $criteria): bool
    {
        $operator = $criteria['operator'] ?? 'AND';
        $rules = $criteria['rules'] ?? [];

        if (empty($rules)) {
            return false;
        }

        $results = [];
        foreach ($rules as $rule) {
            $results[] = $this->evaluateRule($contact, $rule);
        }

        if ($operator === 'OR') {
            return in_array(true, $results);
        }

        return !in_array(false, $results); // AND
    }

    /**
     * Evaluate a single rule for a contact
     */
    private function evaluateRule(Contact $contact, array $rule): bool
    {
        $field = $rule['field'] ?? null;
        $operator = $rule['operator'] ?? null;
        $value = $rule['value'] ?? null;

        if (!$field || !$operator) {
            return false;
        }

        $contactValue = $this->getContactFieldValue($contact, $field);

        return match($operator) {
            'equals' => $contactValue == $value,
            'not_equals' => $contactValue != $value,
            'contains' => is_string($contactValue) && str_contains($contactValue, $value),
            'not_contains' => is_string($contactValue) && !str_contains($contactValue, $value),
            'starts_with' => is_string($contactValue) && str_starts_with($contactValue, $value),
            'ends_with' => is_string($contactValue) && str_ends_with($contactValue, $value),
            'greater_than' => is_numeric($contactValue) && $contactValue > $value,
            'less_than' => is_numeric($contactValue) && $contactValue < $value,
            'in' => is_array($value) && in_array($contactValue, $value),
            'not_in' => is_array($value) && !in_array($contactValue, $value),
            'is_null' => $contactValue === null,
            'is_not_null' => $contactValue !== null,
            default => false
        };
    }

    /**
     * Get a field value from a contact for criteria evaluation
     */
    private function getContactFieldValue(Contact $contact, string $field)
    {
        switch ($field) {
            case 'type':
                return $contact->getType();
            case 'status':
                return $contact->getStatus();
            case 'email':
                $emailChannel = $contact->getPrimaryChannel('email');
                return $emailChannel ? $emailChannel->getIdentifier() : null;
            case 'phone':
                $phoneChannel = $contact->getPrimaryChannel('phone');
                return $phoneChannel ? $phoneChannel->getIdentifier() : null;
            case 'engagement_score':
                return $contact->getEngagementScore();
            case 'created_at':
                return $contact->getCreatedAt();
            case 'last_activity_at':
                return $contact->getLastEngagedAt();
            case 'tags':
                return $contact->getTags();
            default:
                return null;
        }
    }

    /**
     * Refresh memberships for dynamic groups
     */
    public function refreshDynamicGroupMemberships(): int
    {
        $dynamicGroups = $this->findGroupsNeedingEvaluation();
        $refreshed = 0;

        foreach ($dynamicGroups as $group) {
            $this->refreshGroupMembership($group);
            $refreshed++;
        }

        return $refreshed;
    }

    /**
     * Refresh membership for a specific group
     */
    public function refreshGroupMembership(ContactGroup $group): void
    {
        if ($group->getType() === ContactGroup::TYPE_STATIC) {
            return; // Static groups don't auto-refresh
        }

        // Get all contacts
        $contacts = $this->getEntityManager()
            ->getRepository(Contact::class)
            ->findBy(['isActive' => true]);

        // Clear current memberships for dynamic groups
        $group->getContacts()->clear();

        // Re-evaluate all contacts
        foreach ($contacts as $contact) {
            if ($this->contactQualifiesForGroup($contact, $group)) {
                $group->addContact($contact);
            }
        }

        $this->updateContactCount($group);
        $this->save($group, true);
    }

    /**
     * Clean up inactive groups
     */
    public function cleanupInactiveGroups(\DateTimeInterface $before): int
    {
        $inactiveGroups = $this->createQueryBuilder('g')
            ->where('g.isActive = false')
            ->andWhere('g.updatedAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();

        foreach ($inactiveGroups as $group) {
            $this->remove($group);
        }

        if (!empty($inactiveGroups)) {
            $this->getEntityManager()->flush();
        }

        return count($inactiveGroups);
    }
}
