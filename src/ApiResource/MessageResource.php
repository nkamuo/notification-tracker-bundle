<?php

namespace Nkamuo\NotificationTrackerBundle\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\DTO\MessageDTO;

#[ApiResource(
    uriTemplate: '/messages',
    operations: [
        new GetCollection(
            uriTemplate: '/messages',
            description: 'Get collection of messages'
        ),
        new Post(
            uriTemplate: '/messages',
            description: 'Create and send a new message',
            input: MessageDTO::class
        ),
        new Get(
            uriTemplate: '/messages/{id}',
            description: 'Get a message by ID'
        ),
        new Put(
            uriTemplate: '/messages/{id}',
            description: 'Update a message',
            input: MessageDTO::class
        ),
        new Patch(
            uriTemplate: '/messages/{id}',
            description: 'Partially update a message',
            input: MessageDTO::class
        ),
        new Delete(
            uriTemplate: '/messages/{id}',
            description: 'Delete a message'
        )
    ],
    normalizationContext: ['groups' => ['message:read']],
    denormalizationContext: ['groups' => ['message:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'type' => 'exact',
    'status' => 'exact',
    'recipient' => 'partial',
    'subject' => 'partial',
    'priority' => 'exact',
    'source' => 'partial',
    'campaign' => 'partial'
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'sentAt', 'scheduledAt', 'deliveredAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'sentAt', 'priority', 'type'])]
class MessageResource
{
    public function __construct(
        private Message $message
    ) {
    }

    public function getMessage(): Message
    {
        return $this->message;
    }
}
