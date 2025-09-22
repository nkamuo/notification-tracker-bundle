<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\CQRS\Handler;

use Nkamuo\NotificationTrackerBundle\CQRS\Query\QueryInterface;

/**
 * Interface for query handlers
 * 
 * @template T of QueryInterface
 * @template R
 */
interface QueryHandlerInterface
{
    /**
     * @param T $query
     * @return R
     */
    public function __invoke(QueryInterface $query): mixed;
}
