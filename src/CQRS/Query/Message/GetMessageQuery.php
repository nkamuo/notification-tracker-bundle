<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\CQRS\Query\Message;

use Nkamuo\NotificationTrackerBundle\CQRS\Query\QueryInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Query to get a message by ID
 */
readonly class GetMessageQuery implements QueryInterface
{
    public function __construct(
        public Ulid $id,
        public bool $includeContent = false,
        public bool $includeEvents = false,
        public bool $includeAttachments = false
    ) {
    }
}
