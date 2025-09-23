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
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;
use Nkamuo\NotificationTrackerBundle\DTO\ContactDTO;
use Nkamuo\NotificationTrackerBundle\State\ContactProvider;
use Nkamuo\NotificationTrackerBundle\State\ContactProcessor;

#[ApiResource(
    uriTemplate: '/contacts',
    shortName: 'Contact',
    operations: [
        new GetCollection(
            uriTemplate: '/contacts',
            description: 'Get collection of contacts'
        ),
        new Post(
            uriTemplate: '/contacts',
            description: 'Create a new contact',
            input: ContactDTO::class
        ),
        new Get(
            uriTemplate: '/contacts/{id}',
            description: 'Get a contact by ID'
        ),
        new Put(
            uriTemplate: '/contacts/{id}',
            description: 'Update a contact',
            input: ContactDTO::class
        ),
        new Patch(
            uriTemplate: '/contacts/{id}',
            description: 'Partially update a contact',
            input: ContactDTO::class
        ),
        new Delete(
            uriTemplate: '/contacts/{id}',
            description: 'Delete a contact'
        )
    ],
    normalizationContext: ['groups' => ['contact:read']],
    denormalizationContext: ['groups' => ['contact:write']],
    routePrefix: '/notification-tracker',
    provider: ContactProvider::class,
    processor: ContactProcessor::class
)]
#[ApiFilter(SearchFilter::class, properties: [
    'type' => 'exact',
    'status' => 'exact',
    'firstName' => 'partial',
    'lastName' => 'partial',
    'displayName' => 'partial',
    'organizationName' => 'partial'
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'updatedAt', 'lastEngagedAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'updatedAt', 'lastEngagedAt', 'engagementScore', 'firstName', 'lastName'])]
#[ApiFilter(RangeFilter::class, properties: ['engagementScore'])]
class ContactResource
{
    public function __construct(
        private Contact $contact
    ) {
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }
}
