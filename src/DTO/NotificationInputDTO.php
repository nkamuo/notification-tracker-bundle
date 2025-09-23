<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\DTO;

use Nkamuo\NotificationTrackerBundle\Enum\NotificationDirection;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * DTO for creating and updating notifications with proper enum validation.
 */
class NotificationInputDTO
{
    #[Assert\NotBlank(groups: ['notification:create'])]
    #[Groups(['notification:create', 'notification:write'])]
    public ?string $type = null;

    #[Assert\Choice(['low', 'normal', 'high', 'urgent'])]
    #[Groups(['notification:create', 'notification:write'])]
    public string $importance = 'normal';

    #[Assert\NotBlank(groups: ['notification:create'])]
    #[Groups(['notification:create', 'notification:write'])]
    public ?string $subject = null;

    #[Groups(['notification:create', 'notification:write'])]
    public ?string $content = null;

    #[Assert\All([
        new Assert\Choice(['email', 'sms', 'slack', 'telegram', 'push', 'whatsapp', 'discord', 'teams', 'webhook'])
    ])]
    #[Groups(['notification:create', 'notification:write'])]
    public array $channels = [];

    #[Groups(['notification:create', 'notification:write'])]
    public array $recipients = [];

    #[Assert\Callback]
    public function validateStatus(ExecutionContextInterface $context): void
    {
        if ($this->status !== null && !in_array($this->status, NotificationStatus::values())) {
            $context->buildViolation('Invalid status. Valid values are: {{ choices }}.')
                ->setParameter('{{ choices }}', implode(', ', NotificationStatus::values()))
                ->addViolation();
        }
    }

    #[Groups(['notification:create', 'notification:write'])]
    public ?string $status = null;

    #[Assert\Callback]
    public function validateDirection(ExecutionContextInterface $context): void
    {
        if ($this->direction !== null && !in_array($this->direction, NotificationDirection::values())) {
            $context->buildViolation('Invalid direction. Valid values are: {{ choices }}.')
                ->setParameter('{{ choices }}', implode(', ', NotificationDirection::values()))
                ->addViolation();
        }
    }

    #[Groups(['notification:create', 'notification:write'])]
    public ?string $direction = null;

    #[Groups(['notification:create', 'notification:write'])]
    public ?\DateTimeInterface $scheduledAt = null;

    #[Groups(['notification:create', 'notification:write'])]
    public ?array $metadata = null;

    #[Groups(['notification:create', 'notification:write'])]
    public ?array $labels = null;

    #[Groups(['notification:create', 'notification:write'])]
    public ?array $channelSettings = null;

    /**
     * Validate that for scheduled notifications, scheduledAt is required and in the future
     */
    #[Assert\Callback]
    public function validateScheduledNotification(ExecutionContextInterface $context): void
    {
        if ($this->status === NotificationStatus::SCHEDULED->value) {
            if ($this->scheduledAt === null) {
                $context->buildViolation('scheduledAt is required for scheduled notifications.')
                    ->atPath('scheduledAt')
                    ->addViolation();
            } elseif ($this->scheduledAt <= new \DateTimeImmutable()) {
                $context->buildViolation('scheduledAt must be in the future for scheduled notifications.')
                    ->atPath('scheduledAt')
                    ->addViolation();
            }
        }
    }

    /**
     * Validate that required fields are provided based on status
     */
    #[Assert\Callback]
    public function validateRequiredFieldsByStatus(ExecutionContextInterface $context): void
    {
        // Draft notifications can have minimal fields
        if ($this->status === NotificationStatus::DRAFT->value) {
            return;
        }

        // Non-draft notifications need channels and recipients
        if (empty($this->channels)) {
            $context->buildViolation('At least one channel is required for non-draft notifications.')
                ->atPath('channels')
                ->addViolation();
        }

        if (empty($this->recipients)) {
            $context->buildViolation('At least one recipient is required for non-draft notifications.')
                ->atPath('recipients')
                ->addViolation();
        }
    }

    /**
     * Validate recipients format based on channels
     */
    #[Assert\Callback]
    public function validateRecipients(ExecutionContextInterface $context): void
    {
        if (!is_array($this->recipients)) {
            return;
        }

        foreach ($this->recipients as $index => $recipient) {
            if (!is_array($recipient)) {
                $context->buildViolation('Recipient must be an array.')
                    ->atPath("recipients[{$index}]")
                    ->addViolation();
                continue;
            }

            if (!isset($recipient['channel'])) {
                $context->buildViolation('Recipient must specify a channel.')
                    ->atPath("recipients[{$index}].channel")
                    ->addViolation();
                continue;
            }

            // Validate channel-specific recipient format
            $channel = $recipient['channel'];
            switch ($channel) {
                case 'email':
                    if (!isset($recipient['email']) && !isset($recipient['address'])) {
                        $context->buildViolation('Email recipient must have email or address field.')
                            ->atPath("recipients[{$index}]")
                            ->addViolation();
                    }
                    break;
                case 'sms':
                    if (!isset($recipient['phone']) && !isset($recipient['address'])) {
                        $context->buildViolation('SMS recipient must have phone or address field.')
                            ->atPath("recipients[{$index}]")
                            ->addViolation();
                    }
                    break;
                case 'slack':
                    if (!isset($recipient['channel']) && !isset($recipient['address']) && !isset($recipient['user_id'])) {
                        $context->buildViolation('Slack recipient must have channel, address, or user_id field.')
                            ->atPath("recipients[{$index}]")
                            ->addViolation();
                    }
                    break;
            }
        }
    }
}
