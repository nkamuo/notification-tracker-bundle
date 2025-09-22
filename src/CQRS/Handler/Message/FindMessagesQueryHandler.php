<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\CQRS\Handler\Message;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Nkamuo\NotificationTrackerBundle\CQRS\Handler\QueryHandlerInterface;
use Nkamuo\NotificationTrackerBundle\CQRS\Query\Message\FindMessagesQuery;
use Nkamuo\NotificationTrackerBundle\CQRS\Query\QueryInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Message;

/**
 * Handler for FindMessagesQuery
 */
class FindMessagesQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @param FindMessagesQuery $query
     * @return array{messages: Message[], total: int}
     */
    public function __invoke(QueryInterface $query): array
    {
        assert($query instanceof FindMessagesQuery);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(Message::class, 'm')
            ->leftJoin('m.labels', 'l');

        $this->applyFilters($qb, $query);
        $this->applyOrdering($qb, $query);

        // Get total count before applying limit/offset
        $countQb = clone $qb;
        $countQb->select('COUNT(DISTINCT m.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        // Apply pagination
        $qb->setFirstResult($query->offset)
           ->setMaxResults($query->limit);

        $messages = $qb->getQuery()->getResult();

        return [
            'messages' => $messages,
            'total' => $total,
        ];
    }

    private function applyFilters(QueryBuilder $qb, FindMessagesQuery $query): void
    {
        if ($query->statuses !== null && count($query->statuses) > 0) {
            $qb->andWhere('m.status IN (:statuses)')
               ->setParameter('statuses', $query->statuses);
        }

        if ($query->direction !== null) {
            $qb->andWhere('m.direction = :direction')
               ->setParameter('direction', $query->direction);
        }

        if ($query->labelNames !== null && count($query->labelNames) > 0) {
            $qb->andWhere('l.name IN (:labelNames)')
               ->setParameter('labelNames', $query->labelNames);
        }

        if ($query->transportName !== null) {
            $qb->andWhere('m.transportName = :transportName')
               ->setParameter('transportName', $query->transportName);
        }

        if ($query->search !== null) {
            $qb->leftJoin('m.content', 'c')
               ->leftJoin('m.recipients', 'r')
               ->andWhere('c.subject LIKE :search OR c.textBody LIKE :search OR c.htmlBody LIKE :search OR r.address LIKE :search')
               ->setParameter('search', '%' . $query->search . '%');
        }

        if ($query->createdAfter !== null) {
            $qb->andWhere('m.createdAt >= :createdAfter')
               ->setParameter('createdAfter', $query->createdAfter);
        }

        if ($query->createdBefore !== null) {
            $qb->andWhere('m.createdAt <= :createdBefore')
               ->setParameter('createdBefore', $query->createdBefore);
        }
    }

    private function applyOrdering(QueryBuilder $qb, FindMessagesQuery $query): void
    {
        foreach ($query->orderBy as $field => $direction) {
            $qb->addOrderBy('m.' . $field, $direction);
        }
    }
}
