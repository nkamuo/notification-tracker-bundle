<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\CQRS\Handler;

use Nkamuo\NotificationTrackerBundle\CQRS\Command\CommandInterface;

/**
 * Interface for command handlers
 * 
 * @template T of CommandInterface
 * @template R
 */
interface CommandHandlerInterface
{
    /**
     * @param T $command
     * @return R
     */
    public function __invoke(CommandInterface $command): mixed;
}
