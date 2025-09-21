<?php

namespace Nkamuo\NotificationTrackerBundle\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

class ContactChannelDTO
{
    #[Assert\NotBlank]
    #[Groups(['contact_channel:write'])]
    public ?string $contactId = null;

    #[Assert\NotBlank]
    #[Assert\Choice(['email', 'phone', 'sms', 'telegram', 'slack', 'whatsapp', 'discord', 'teams', 'push', 'webhook', 'custom'])]
    #[Groups(['contact_channel:write'])]
    public ?string $type = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['contact_channel:write'])]
    public ?string $identifier = null;

    #[Assert\Length(max: 255)]
    #[Groups(['contact_channel:write'])]
    public ?string $displayName = null;

    #[Groups(['contact_channel:write'])]
    public ?bool $isPrimary = null;

    #[Groups(['contact_channel:write'])]
    public ?bool $isActive = null;

    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['contact_channel:write'])]
    public ?int $priority = null;

    #[Groups(['contact_channel:write'])]
    public ?array $metadata = null;

    #[Groups(['contact_channel:write'])]
    public ?array $capabilities = null;
}
