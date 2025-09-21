<?php

namespace Nkamuo\NotificationTrackerBundle\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

class ContactGroupDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['contact_group:write'])]
    public ?string $name = null;

    #[Groups(['contact_group:write'])]
    public ?string $description = null;

    #[Assert\Choice(['static', 'dynamic', 'behavior'])]
    #[Groups(['contact_group:write'])]
    public ?string $type = null;

    #[Groups(['contact_group:write'])]
    public ?string $parentGroupId = null;

    #[Groups(['contact_group:write'])]
    public ?array $criteria = null;

    #[Groups(['contact_group:write'])]
    public ?array $tags = null;

    #[Groups(['contact_group:write'])]
    public ?bool $isActive = null;

    #[Groups(['contact_group:write'])]
    public ?array $contactIds = null; // For static groups
}
