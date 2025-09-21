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
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Nkamuo\NotificationTrackerBundle\Entity\ContactChannel;
use Nkamuo\NotificationTrackerBundle\DTO\ContactChannelDTO;

#[ApiResource(
    uriTemplate: '/contact-channels',
    operations: [
        new GetCollection(
            uriTemplate: '/contact-channels',
            description: 'Get collection of contact channels'
        ),
        new Post(
            uriTemplate: '/contact-channels',
            description: 'Create a new contact channel',
            input: ContactChannelDTO::class
        ),
        new Get(
            uriTemplate: '/contact-channels/{id}',
            description: 'Get a contact channel by ID'
        ),
        new Put(
            uriTemplate: '/contact-channels/{id}',
            description: 'Update a contact channel',
            input: ContactChannelDTO::class
        ),
        new Patch(
            uriTemplate: '/contact-channels/{id}',
            description: 'Partially update a contact channel',
            input: ContactChannelDTO::class
        ),
        new Delete(
            uriTemplate: '/contact-channels/{id}',
            description: 'Delete a contact channel'
        )
    ],
    normalizationContext: ['groups' => ['contact_channel:read']],
    denormalizationContext: ['groups' => ['contact_channel:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'type' => 'exact',
    'identifier' => 'partial',
    'contact.id' => 'exact',
    'status' => 'exact'
])]
#[ApiFilter(BooleanFilter::class, properties: ['isPrimary', 'isActive', 'isVerified'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'lastUsedAt', 'priority', 'deliveryRate'])]
class ContactChannelResource
{
    public function __construct(
        private ContactChannel $contactChannel
    ) {
    }

    public function getContactChannel(): ContactChannel
    {
        return $this->contactChannel;
    }
}
