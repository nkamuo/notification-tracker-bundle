<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\CQRS\Query\Message;

use Nkamuo\NotificationTrackerBundle\CQRS\Query\QueryInterface;

/**
 * Query to find messages by various criteria
 */
readonly class FindMessagesQuery implements QueryInterface
{
    public function __construct(
        public ?array $statuses = null,
        public ?string $direction = null,
        public ?array $labelNames = null,
        public ?string $transportName = null,
        public ?string $search = null,
        public ?\DateTimeImmutable $createdAfter = null,
        public ?\DateTimeImmutable $createdBefore = null,
        public int $limit = 50,
        public int $offset = 0,
        public array $orderBy = ['createdAt' => 'DESC']
    ) {
    }
}
