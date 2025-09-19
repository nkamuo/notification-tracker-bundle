<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'notification_tracker_slack_messages')]
#[ApiResource(
    shortName: 'SlackMessage',
    description: 'Slack message tracking',
    normalizationContext: ['groups' => ['message:read', 'slack:read']],
    denormalizationContext: ['groups' => ['message:write', 'slack:write']],
)]
class SlackMessage extends Message
{
    #[ORM\Column(length: 100)]
    #[Groups(['slack:read', 'slack:write'])]
    private string $channel;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['slack:read', 'slack:write'])]
    private ?string $threadTs = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['slack:read', 'slack:write'])]
    private ?array $blocks = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['slack:read', 'slack:write'])]
    private ?array $attachments = null;

    public function getType(): string
    {
        return 'slack';
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): self
    {
        $this->channel = $channel;
        return $this;
    }

    public function getThreadTs(): ?string
    {
        return $this->threadTs;
    }

    public function setThreadTs(?string $threadTs): self
    {
        $this->threadTs = $threadTs;
        return $this;
    }

    public function getBlocks(): ?array
    {
        return $this->blocks;
    }

    public function setBlocks(?array $blocks): self
    {
        $this->blocks = $blocks;
        return $this;
    }

    public function getAttachments(): ?array
    {
        return $this->attachments;
    }

    public function setAttachments(?array $attachments): self
    {
        $this->attachments = $attachments;
        return $this;
    }
}