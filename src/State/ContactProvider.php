<?php

namespace Nkamuo\NotificationTrackerBundle\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Nkamuo\NotificationTrackerBundle\Repository\ContactRepository;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;

class ContactProvider implements ProviderInterface
{
    public function __construct(
        private ContactRepository $contactRepository
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Handle standard CRUD operations
        if (isset($uriVariables['id'])) {
            return $this->contactRepository->find($uriVariables['id']);
        }

        // Collection
        return $this->contactRepository->findBy([], ['createdAt' => 'DESC']);
    }
}
