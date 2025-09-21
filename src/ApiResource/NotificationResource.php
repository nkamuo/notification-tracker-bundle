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
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\DTO\NotificationDTO;

#[ApiResource(
    uriTemplate: '/notifications',
    operations: [
        new GetCollection(
            uriTemplate: '/notifications',
            description: 'Get collection of notifications'
        ),
        new Post(
            uriTemplate: '/notifications',
            description: 'Create and send a new notification',
            input: NotificationDTO::class
        ),
        new Get(
            uriTemplate: '/notifications/{id}',
            description: 'Get a notification by ID'
        ),
        new Put(
            uriTemplate: '/notifications/{id}',
            description: 'Update a notification',
            input: NotificationDTO::class
        ),
        new Patch(
            uriTemplate: '/notifications/{id}',
            description: 'Partially update a notification',
            input: NotificationDTO::class
        ),
        new Delete(
            uriTemplate: '/notifications/{id}',
            description: 'Delete a notification'
        )
    ],
    normalizationContext: ['groups' => ['notification:read']],
    denormalizationContext: ['groups' => ['notification:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'type' => 'exact',
    'status' => 'exact',
    'title' => 'partial',
    'priority' => 'exact',
    'category' => 'exact',
    'source' => 'partial',
    'campaign' => 'partial'
])]
#[ApiFilter(BooleanFilter::class, properties: ['requiresAcknowledgment'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'sentAt', 'scheduledAt', 'expiresAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'sentAt', 'priority', 'type'])]
class NotificationResource
{
    public function __construct(
        private Notification $notification
    ) {
    }

    public function getNotification(): Notification
    {
        return $this->notification;
    }
}
