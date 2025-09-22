<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\CQRS\Handler\Message;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\CQRS\Handler\QueryHandlerInterface;
use Nkamuo\NotificationTrackerBundle\CQRS\Query\Message\GetMessageQuery;
use Nkamuo\NotificationTrackerBundle\CQRS\Query\QueryInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Message;

/**
 * Handler for GetMessageQuery
 */
class GetMessageQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @param GetMessageQuery $query
     * @return Message|null
     */
    public function __invoke(QueryInterface $query): ?Message
    {
        assert($query instanceof GetMessageQuery);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(Message::class, 'm')
            ->where('m.id = :id')
            ->setParameter('id', $query->id);

        // Include related data based on query parameters
        if ($query->includeContent) {
            $qb->leftJoin('m.content', 'c')->addSelect('c');
        }

        if ($query->includeEvents) {
            $qb->leftJoin('m.events', 'e')->addSelect('e');
        }

        if ($query->includeAttachments) {
            $qb->leftJoin('m.attachments', 'a')->addSelect('a');
        }

        // Always include labels and recipients
        $qb->leftJoin('m.labels', 'l')->addSelect('l')
           ->leftJoin('m.recipients', 'r')->addSelect('r');

        return $qb->getQuery()->getOneOrNullResult();
    }
}
