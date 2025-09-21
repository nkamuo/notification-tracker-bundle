<?php

namespace Nkamuo\NotificationTrackerBundle\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

class ContactChannelPreferenceDTO
{
    #[Assert\NotBlank]
    #[Groups(['contact_preference:write'])]
    public ?string $contactChannelId = null;

    #[Groups(['contact_preference:write'])]
    public ?string $category = null;

    #[Groups(['contact_preference:write'])]
    public ?bool $allowNotifications = null;

    #[Groups(['contact_preference:write'])]
    public ?bool $allowTransactional = null;

    #[Groups(['contact_preference:write'])]
    public ?bool $allowMarketing = null;

    #[Groups(['contact_preference:write'])]
    public ?bool $allowPromotional = null;

    #[Assert\Choice(['immediate', 'hourly', 'daily', 'weekly', 'monthly', 'never'])]
    #[Groups(['contact_preference:write'])]
    public ?string $frequency = null;

    #[Assert\Choice(['high', 'medium', 'low', 'all'])]
    #[Groups(['contact_preference:write'])]
    public ?string $minimumPriority = null;

    #[Groups(['contact_preference:write'])]
    public ?array $quietHours = null;

    #[Groups(['contact_preference:write'])]
    public ?array $allowedCategories = null;

    #[Groups(['contact_preference:write'])]
    public ?array $blockedCategories = null;

    #[Groups(['contact_preference:write'])]
    public ?array $allowedSenders = null;

    #[Groups(['contact_preference:write'])]
    public ?array $blockedSenders = null;

    #[Assert\Range(min: 0)]
    #[Groups(['contact_preference:write'])]
    public ?int $maxMessagesPerDay = null;

    #[Assert\Range(min: 0)]
    #[Groups(['contact_preference:write'])]
    public ?int $maxMessagesPerWeek = null;

    #[Groups(['contact_preference:write'])]
    public ?array $customRules = null;
}
