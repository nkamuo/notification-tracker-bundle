<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'notification_tracker_sms_messages')]
#[ApiResource(
    shortName: 'SmsMessage',
    description: 'SMS message tracking',
    normalizationContext: ['groups' => ['message:read', 'sms:read']],
    denormalizationContext: ['groups' => ['message:write', 'sms:write']],
)]
class SmsMessage extends Message
{
    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['sms:read', 'sms:write'])]
    private ?string $fromNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['sms:read'])]
    private ?string $providerMessageId = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['sms:read'])]
    private int $segmentsCount = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Groups(['sms:read'])]
    private ?string $cost = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['sms:read', 'sms:write'])]
    private ?string $encoding = 'GSM';

    public function getType(): string
    {
        return 'sms';
    }

    public function getFromNumber(): ?string
    {
        return $this->fromNumber;
    }

    public function setFromNumber(?string $fromNumber): self
    {
        $this->fromNumber = $fromNumber;
        return $this;
    }

    public function getProviderMessageId(): ?string
    {
        return $this->providerMessageId;
    }

    public function setProviderMessageId(?string $providerMessageId): self
    {
        $this->providerMessageId = $providerMessageId;
        return $this;
    }

    public function getSegmentsCount(): int
    {
        return $this->segmentsCount;
    }

    public function setSegmentsCount(int $segmentsCount): self
    {
        $this->segmentsCount = $segmentsCount;
        return $this;
    }

    public function getCost(): ?string
    {
        return $this->cost;
    }

    public function setCost(?string $cost): self
    {
        $this->cost = $cost;
        return $this;
    }

    public function getEncoding(): ?string
    {
        return $this->encoding;
    }

    public function setEncoding(?string $encoding): self
    {
        $this->encoding = $encoding;
        return $this;
    }
}