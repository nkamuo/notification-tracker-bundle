<?php

namespace Nkamuo\NotificationTrackerBundle\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

class MessageTemplateDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['template:write'])]
    public ?string $name = null;

    #[Groups(['template:write'])]
    public ?string $description = null;

    #[Assert\NotBlank]
    #[Assert\Choice(['email', 'sms', 'telegram', 'slack', 'whatsapp', 'discord', 'teams', 'push', 'webhook', 'custom'])]
    #[Groups(['template:write'])]
    public ?string $type = null;

    #[Groups(['template:write'])]
    public ?string $subject = null;

    #[Assert\NotBlank]
    #[Groups(['template:write'])]
    public ?string $content = null;

    #[Groups(['template:write'])]
    public ?array $variables = null;

    #[Groups(['template:write'])]
    public ?array $metadata = null;

    #[Groups(['template:write'])]
    public ?string $category = null;

    #[Groups(['template:write'])]
    public ?array $tags = null;

    #[Groups(['template:write'])]
    public ?bool $isActive = null;

    #[Assert\Length(max: 10)]
    #[Groups(['template:write'])]
    public ?string $language = null;

    #[Groups(['template:write'])]
    public ?string $version = null;
}
