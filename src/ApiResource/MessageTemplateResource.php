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
use Nkamuo\NotificationTrackerBundle\Entity\MessageTemplate;
use Nkamuo\NotificationTrackerBundle\DTO\MessageTemplateDTO;

#[ApiResource(
    uriTemplate: '/message-templates',
    operations: [
        new GetCollection(
            uriTemplate: '/message-templates',
            description: 'Get collection of message templates'
        ),
        new Post(
            uriTemplate: '/message-templates',
            description: 'Create a new message template',
            input: MessageTemplateDTO::class
        ),
        new Get(
            uriTemplate: '/message-templates/{id}',
            description: 'Get a message template by ID'
        ),
        new Put(
            uriTemplate: '/message-templates/{id}',
            description: 'Update a message template',
            input: MessageTemplateDTO::class
        ),
        new Patch(
            uriTemplate: '/message-templates/{id}',
            description: 'Partially update a message template',
            input: MessageTemplateDTO::class
        ),
        new Delete(
            uriTemplate: '/message-templates/{id}',
            description: 'Delete a message template'
        )
    ],
    normalizationContext: ['groups' => ['template:read']],
    denormalizationContext: ['groups' => ['template:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'type' => 'exact',
    'category' => 'exact',
    'language' => 'exact',
    'version' => 'exact'
])]
#[ApiFilter(BooleanFilter::class, properties: ['isActive'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'createdAt', 'updatedAt', 'type'])]
class MessageTemplateResource
{
    public function __construct(
        private MessageTemplate $messageTemplate
    ) {
    }

    public function getMessageTemplate(): MessageTemplate
    {
        return $this->messageTemplate;
    }
}
