<?php

namespace Nkamuo\NotificationTrackerBundle\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

class NotificationDTO
{
    #[Assert\NotBlank]
    #[Groups(['notification:write'])]
    public ?string $title = null;

    #[Assert\NotBlank]
    #[Groups(['notification:write'])]
    public ?string $content = null;

    #[Assert\Choice(['info', 'success', 'warning', 'error'])]
    #[Groups(['notification:write'])]
    public ?string $type = null;

    #[Assert\Choice(['low', 'normal', 'high', 'urgent'])]
    #[Groups(['notification:write'])]
    public ?string $priority = null;

    #[Groups(['notification:write'])]
    public ?array $recipients = null;

    #[Groups(['notification:write'])]
    public ?array $channels = null;

    #[Groups(['notification:write'])]
    public ?array $metadata = null;

    #[Groups(['notification:write'])]
    public ?\DateTimeInterface $scheduledAt = null;

    #[Groups(['notification:write'])]
    public ?\DateTimeInterface $expiresAt = null;

    #[Groups(['notification:write'])]
    public ?string $category = null;

    #[Groups(['notification:write'])]
    public ?array $tags = null;

    #[Groups(['notification:write'])]
    public ?string $source = null;

    #[Groups(['notification:write'])]
    public ?string $campaign = null;

    #[Groups(['notification:write'])]
    public ?bool $requiresAcknowledgment = null;

    #[Groups(['notification:write'])]
    public ?array $actions = null;
}
