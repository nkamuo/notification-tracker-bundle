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
use Nkamuo\NotificationTrackerBundle\Entity\ContactChannelPreference;
use Nkamuo\NotificationTrackerBundle\DTO\ContactChannelPreferenceDTO;

#[ApiResource(
    uriTemplate: '/contact-channel-preferences',
    operations: [
        new GetCollection(
            uriTemplate: '/contact-channel-preferences',
            description: 'Get collection of contact channel preferences'
        ),
        new Post(
            uriTemplate: '/contact-channel-preferences',
            description: 'Create a new contact channel preference',
            input: ContactChannelPreferenceDTO::class
        ),
        new Get(
            uriTemplate: '/contact-channel-preferences/{id}',
            description: 'Get a contact channel preference by ID'
        ),
        new Put(
            uriTemplate: '/contact-channel-preferences/{id}',
            description: 'Update a contact channel preference',
            input: ContactChannelPreferenceDTO::class
        ),
        new Patch(
            uriTemplate: '/contact-channel-preferences/{id}',
            description: 'Partially update a contact channel preference',
            input: ContactChannelPreferenceDTO::class
        ),
        new Delete(
            uriTemplate: '/contact-channel-preferences/{id}',
            description: 'Delete a contact channel preference'
        )
    ],
    normalizationContext: ['groups' => ['contact_preference:read']],
    denormalizationContext: ['groups' => ['contact_preference:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'category' => 'exact',
    'frequency' => 'exact',
    'minimumPriority' => 'exact',
    'contactChannel.id' => 'exact',
    'contactChannel.contact.id' => 'exact'
])]
#[ApiFilter(BooleanFilter::class, properties: [
    'allowNotifications', 
    'allowTransactional', 
    'allowMarketing', 
    'allowPromotional'
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'updatedAt'])]
class ContactChannelPreferenceResource
{
    public function __construct(
        private ContactChannelPreference $contactChannelPreference
    ) {
    }

    public function getContactChannelPreference(): ContactChannelPreference
    {
        return $this->contactChannelPreference;
    }
}
