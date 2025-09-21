<?php

namespace Nkamuo\NotificationTrackerBundle\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

class ContactDTO
{
    #[Assert\Choice(['individual', 'organization', 'department', 'role', 'system'])]
    #[Groups(['contact:write'])]
    public ?string $type = null;

    #[Assert\Length(max: 255)]
    #[Groups(['contact:write'])]
    public ?string $firstName = null;

    #[Assert\Length(max: 255)]
    #[Groups(['contact:write'])]
    public ?string $lastName = null;

    #[Assert\Length(max: 255)]
    #[Groups(['contact:write'])]
    public ?string $displayName = null;

    #[Assert\Length(max: 255)]
    #[Groups(['contact:write'])]
    public ?string $organizationName = null;

    #[Assert\Length(max: 255)]
    #[Groups(['contact:write'])]
    public ?string $jobTitle = null;

    #[Assert\Length(max: 255)]
    #[Groups(['contact:write'])]
    public ?string $department = null;

    #[Assert\Language]
    #[Groups(['contact:write'])]
    public ?string $preferredLanguage = null;

    #[Assert\Timezone]
    #[Groups(['contact:write'])]
    public ?string $timezone = null;

    #[Groups(['contact:write'])]
    public ?array $tags = null;

    #[Groups(['contact:write'])]
    public ?array $customFields = null;

    #[Groups(['contact:write'])]
    public ?string $notes = null;

    #[Assert\Choice(['active', 'inactive', 'blocked', 'pending_verification', 'merged'])]
    #[Groups(['contact:write'])]
    public ?string $status = null;

    #[Groups(['contact:write'])]
    public ?bool $isActive = null;
}
