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
use Nkamuo\NotificationTrackerBundle\Entity\ContactGroup;
use Nkamuo\NotificationTrackerBundle\DTO\ContactGroupDTO;

#[ApiResource(
    uriTemplate: '/contact-groups',
    shortName: 'ContactGroup',
    operations: [
        new GetCollection(
            uriTemplate: '/contact-groups',
            description: 'Get collection of contact groups'
        ),
        new Post(
            uriTemplate: '/contact-groups',
            description: 'Create a new contact group',
            input: ContactGroupDTO::class
        ),
        new Get(
            uriTemplate: '/contact-groups/{id}',
            description: 'Get a contact group by ID'
        ),
        new Put(
            uriTemplate: '/contact-groups/{id}',
            description: 'Update a contact group',
            input: ContactGroupDTO::class
        ),
        new Patch(
            uriTemplate: '/contact-groups/{id}',
            description: 'Partially update a contact group',
            input: ContactGroupDTO::class
        ),
        new Delete(
            uriTemplate: '/contact-groups/{id}',
            description: 'Delete a contact group'
        )
    ],
    normalizationContext: ['groups' => ['contact_group:read']],
    denormalizationContext: ['groups' => ['contact_group:write']],
    routePrefix: '/notification-tracker',
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'type' => 'exact',
    'parentGroup.id' => 'exact'
])]
#[ApiFilter(BooleanFilter::class, properties: ['isActive'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'createdAt', 'contactCount'])]
class ContactGroupResource
{
    public function __construct(
        private ContactGroup $contactGroup
    ) {
    }

    public function getContactGroup(): ContactGroup
    {
        return $this->contactGroup;
    }
}
