<?php

namespace Nkamuo\NotificationTrackerBundle\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

class MessageDTO
{
    #[Assert\NotBlank]
    #[Assert\Choice(['email', 'sms', 'telegram', 'slack', 'whatsapp', 'discord', 'teams', 'push', 'webhook', 'custom'])]
    #[Groups(['message:write'])]
    public ?string $type = null;

    #[Assert\NotBlank]
    #[Groups(['message:write'])]
    public ?string $recipient = null;

    #[Groups(['message:write'])]
    public ?string $subject = null;

    #[Assert\NotBlank]
    #[Groups(['message:write'])]
    public ?string $content = null;

    #[Assert\Choice(['low', 'normal', 'high', 'urgent'])]
    #[Groups(['message:write'])]
    public ?string $priority = null;

    #[Groups(['message:write'])]
    public ?array $metadata = null;

    #[Groups(['message:write'])]
    public ?\DateTimeInterface $scheduledAt = null;

    #[Groups(['message:write'])]
    public ?string $templateId = null;

    #[Groups(['message:write'])]
    public ?array $templateVariables = null;

    #[Groups(['message:write'])]
    public ?array $attachments = null;

    #[Groups(['message:write'])]
    public ?string $tags = null;

    #[Groups(['message:write'])]
    public ?string $source = null;

    #[Groups(['message:write'])]
    public ?string $campaign = null;
}
