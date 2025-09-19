<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\MessageTemplate;

/**
 * @extends ServiceEntityRepository<MessageTemplate>
 * @method MessageTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method MessageTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method MessageTemplate[]    findAll()
 * @method MessageTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageTemplate::class);
    }

    public function findOneByName(string $name): ?MessageTemplate
    {
        return $this->createQueryBuilder('mt')
            ->andWhere('mt.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return MessageTemplate[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('mt')
            ->andWhere('mt.type = :type')
            ->setParameter('type', $type)
            ->orderBy('mt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageTemplate[]
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('mt')
            ->andWhere('mt.category = :category')
            ->setParameter('category', $category)
            ->orderBy('mt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageTemplate[]
     */
    public function findByLanguage(string $language): array
    {
        return $this->createQueryBuilder('mt')
            ->andWhere('mt.language = :language')
            ->setParameter('language', $language)
            ->orderBy('mt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageTemplate[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('mt')
            ->andWhere('mt.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('mt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageTemplate[]
     */
    public function findInactive(): array
    {
        return $this->createQueryBuilder('mt')
            ->andWhere('mt.isActive = :active')
            ->setParameter('active', false)
            ->orderBy('mt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageTemplate[]
     */
    public function findByTypeAndLanguage(string $type, string $language): array
    {
        return $this->createQueryBuilder('mt')
            ->andWhere('mt.type = :type')
            ->andWhere('mt.language = :language')
            ->setParameter('type', $type)
            ->setParameter('language', $language)
            ->orderBy('mt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageTemplate[]
     */
    public function findActiveByCategoryAndLanguage(string $category, string $language): array
    {
        return $this->createQueryBuilder('mt')
            ->andWhere('mt.category = :category')
            ->andWhere('mt.language = :language')
            ->andWhere('mt.isActive = :active')
            ->setParameter('category', $category)
            ->setParameter('language', $language)
            ->setParameter('active', true)
            ->orderBy('mt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageTemplate[]
     */
    public function findActiveByType(string $type): array
    {
        return $this->createQueryBuilder('mt')
            ->andWhere('mt.type = :type')
            ->andWhere('mt.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('mt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search templates by name or description
     * @return MessageTemplate[]
     */
    public function searchByNameOrDescription(string $searchTerm): array
    {
        return $this->createQueryBuilder('mt')
            ->andWhere('mt.name LIKE :search OR mt.description LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('mt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search in template content (subject, body, etc.)
     * @return MessageTemplate[]
     */
    public function searchInContent(string $searchTerm): array
    {
        return $this->createQueryBuilder('mt')
            ->andWhere('mt.subject LIKE :search OR mt.bodyText LIKE :search OR mt.bodyHtml LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('mt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find templates with specific variables in content
     * @return MessageTemplate[]
     */
    public function findWithVariable(string $variable): array
    {
        $searchPattern = '%{{ ' . $variable . ' }}%';
        return $this->createQueryBuilder('mt')
            ->andWhere('mt.subject LIKE :search OR mt.bodyText LIKE :search OR mt.bodyHtml LIKE :search')
            ->setParameter('search', $searchPattern)
            ->orderBy('mt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find templates updated after a specific date
     * @return MessageTemplate[]
     */
    public function findUpdatedAfter(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('mt')
            ->andWhere('mt.updatedAt > :date')
            ->setParameter('date', $date)
            ->orderBy('mt.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find templates created in a date range
     * @return MessageTemplate[]
     */
    public function findCreatedInRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('mt')
            ->andWhere('mt.createdAt >= :from')
            ->andWhere('mt.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('mt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get template statistics by type
     * @return array<string, int>
     */
    public function getStatsByType(): array
    {
        $result = $this->createQueryBuilder('mt')
            ->select('mt.type, COUNT(mt.id) as count')
            ->groupBy('mt.type')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['type']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get template statistics by category
     * @return array<string, int>
     */
    public function getStatsByCategory(): array
    {
        $result = $this->createQueryBuilder('mt')
            ->select('mt.category, COUNT(mt.id) as count')
            ->groupBy('mt.category')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['category']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get template statistics by language
     * @return array<string, int>
     */
    public function getStatsByLanguage(): array
    {
        $result = $this->createQueryBuilder('mt')
            ->select('mt.language, COUNT(mt.id) as count')
            ->groupBy('mt.language')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['language']] = (int) $row['count'];
        }

        return $stats;
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('mt')
            ->select('COUNT(mt.id)')
            ->andWhere('mt.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countInactive(): int
    {
        return (int) $this->createQueryBuilder('mt')
            ->select('COUNT(mt.id)')
            ->andWhere('mt.isActive = :active')
            ->setParameter('active', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find most recently created templates
     * @return MessageTemplate[]
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('mt')
            ->orderBy('mt.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find most recently updated templates
     * @return MessageTemplate[]
     */
    public function findRecentlyUpdated(int $limit = 10): array
    {
        return $this->createQueryBuilder('mt')
            ->orderBy('mt.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
