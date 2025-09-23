<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\DTO;

use Nkamuo\NotificationTrackerBundle\Enum\NotificationDirection;
use Nkamuo\NotificationTrackerBundle\Enum\MessageStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * DTO for creating and updating messages with proper enum validation.
 */
class MessageInputDTO
{
    #[Assert\Choice(['email', 'sms', 'slack', 'telegram', 'push', 'whatsapp', 'discord', 'teams', 'webhook'])]
    #[Groups(['message:create', 'message:write'])]
    public ?string $channel = null;

    #[Groups(['message:create', 'message:write'])]
    public ?string $subject = null;

    #[Groups(['message:create', 'message:write'])]
    public ?string $content = null;

    #[Groups(['message:create', 'message:write'])]
    public array $recipients = [];

    #[Assert\Callback]
    public function validateStatus(ExecutionContextInterface $context): void
    {
        if ($this->status !== null && !in_array($this->status, MessageStatus::values())) {
            $context->buildViolation('Invalid status. Valid values are: {{ choices }}.')
                ->setParameter('{{ choices }}', implode(', ', MessageStatus::values()))
                ->addViolation();
        }
    }

    #[Groups(['message:create', 'message:write'])]
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

    #[Groups(['message:create', 'message:write'])]
    public ?string $direction = null;

    #[Groups(['message:create', 'message:write'])]
    public ?\DateTimeInterface $scheduledAt = null;

    #[Groups(['message:create', 'message:write'])]
    public ?array $metadata = null;

    #[Groups(['message:create', 'message:write'])]
    public ?array $labels = null;

    /**
     * Validate recipients format based on channel
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

            // Validate channel-specific recipient format
            switch ($this->channel) {
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

    /**
     * Validate required fields for non-draft messages
     */
    #[Assert\Callback]
    public function validateRequiredFields(ExecutionContextInterface $context): void
    {
        if ($this->status !== MessageStatus::PENDING->value) {
            return;
        }

        if ($this->channel === null) {
            $context->buildViolation('Channel is required.')
                ->atPath('channel')
                ->addViolation();
        }

        if (empty($this->recipients)) {
            $context->buildViolation('At least one recipient is required.')
                ->atPath('recipients')
                ->addViolation();
        }
    }
}
