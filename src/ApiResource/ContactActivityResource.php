<?php

namespace Nkamuo\NotificationTrackerBundle\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Nkamuo\NotificationTrackerBundle\Entity\ContactActivity;

#[ApiResource(
    uriTemplate: '/contact-activities',
    operations: [
        new GetCollection(
            uriTemplate: '/contact-activities',
            description: 'Get collection of contact activities'
        ),
        new Post(
            uriTemplate: '/contact-activities',
            description: 'Create a new contact activity'
        ),
        new Get(
            uriTemplate: '/contact-activities/{id}',
            description: 'Get a contact activity by ID'
        )
    ],
    normalizationContext: ['groups' => ['contact_activity:read']],
    denormalizationContext: ['groups' => ['contact_activity:write']],
    routePrefix: '/notification-tracker',
)]
#[ApiFilter(SearchFilter::class, properties: [
    'activityType' => 'exact',
    'contact.id' => 'exact',
    'source' => 'partial',
    'performedBy' => 'partial',
    'performedByType' => 'exact'
])]
#[ApiFilter(DateFilter::class, properties: ['occurredAt', 'createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['occurredAt', 'createdAt', 'activityType'])]
class ContactActivityResource
{
    public function __construct(
        private ContactActivity $contactActivity
    ) {
    }

    public function getContactActivity(): ContactActivity
    {
        return $this->contactActivity;
    }
}
