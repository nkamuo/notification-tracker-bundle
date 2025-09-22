<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\CQRS;

use Nkamuo\NotificationTrackerBundle\CQRS\Query\QueryInterface;
use Nkamuo\NotificationTrackerBundle\CQRS\Handler\QueryHandlerInterface;
use Psr\Container\ContainerInterface;

/**
 * Simple query bus implementation
 */
class QueryBus
{
    /** @var array<string, string> */
    private array $handlers = [];

    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * Register a query handler
     */
    public function registerHandler(string $queryClass, string $handlerServiceId): void
    {
        $this->handlers[$queryClass] = $handlerServiceId;
    }

    /**
     * Handle a query
     * 
     * @template T
     * @param QueryInterface $query
     * @return T
     */
    public function handle(QueryInterface $query): mixed
    {
        $queryClass = $query::class;
        
        if (!isset($this->handlers[$queryClass])) {
            throw new \RuntimeException(sprintf(
                'No handler registered for query "%s"',
                $queryClass
            ));
        }

        $handlerServiceId = $this->handlers[$queryClass];
        
        if (!$this->container->has($handlerServiceId)) {
            throw new \RuntimeException(sprintf(
                'Handler service "%s" not found for query "%s"',
                $handlerServiceId,
                $queryClass
            ));
        }

        /** @var QueryHandlerInterface $handler */
        $handler = $this->container->get($handlerServiceId);
        
        return $handler($query);
    }
}
