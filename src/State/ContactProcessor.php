<?php

namespace Nkamuo\NotificationTrackerBundle\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Nkamuo\NotificationTrackerBundle\Repository\ContactRepository;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;
use Nkamuo\NotificationTrackerBundle\DTO\ContactDTO;
use Doctrine\ORM\EntityManagerInterface;

class ContactProcessor implements ProcessorInterface
{
    public function __construct(
        private ContactRepository $contactRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if ($data instanceof ContactDTO) {
            $this->processContactDTO($data, $uriVariables, $context);
        } elseif ($data instanceof Contact) {
            $this->processContact($data);
        }
    }

    private function processContactDTO(ContactDTO $dto, array $uriVariables, array $context): Contact
    {
        $contact = null;
        
        // If we have an ID, we're updating
        if (isset($uriVariables['id'])) {
            $contact = $this->contactRepository->find($uriVariables['id']);
            if (!$contact) {
                throw new \InvalidArgumentException('Contact not found');
            }
        } else {
            // Creating new contact
            $contact = new Contact();
        }

        // Map DTO to entity
        if ($dto->type !== null) {
            $contact->setType($dto->type);
        }
        if ($dto->firstName !== null) {
            $contact->setFirstName($dto->firstName);
        }
        if ($dto->lastName !== null) {
            $contact->setLastName($dto->lastName);
        }
        if ($dto->displayName !== null) {
            $contact->setDisplayName($dto->displayName);
        }
        if ($dto->organizationName !== null) {
            $contact->setOrganizationName($dto->organizationName);
        }
        if ($dto->jobTitle !== null) {
            $contact->setJobTitle($dto->jobTitle);
        }
        if ($dto->department !== null) {
            $contact->setDepartment($dto->department);
        }
        if ($dto->preferredLanguage !== null) {
            $contact->setLanguage($dto->preferredLanguage);
        }
        if ($dto->timezone !== null) {
            $contact->setTimezone($dto->timezone);
        }
        if ($dto->tags !== null) {
            $contact->setTags($dto->tags);
        }
        if ($dto->customFields !== null) {
            $contact->setCustomFields($dto->customFields);
        }
        if ($dto->notes !== null) {
            $contact->setNotes($dto->notes);
        }
        if ($dto->status !== null) {
            $contact->setStatus($dto->status);
        }
        // Note: isActive is handled through status

        $this->contactRepository->save($contact, true);
        
        return $contact;
    }

    private function processContact(Contact $contact): void
    {
        $this->contactRepository->save($contact, true);
    }
}
