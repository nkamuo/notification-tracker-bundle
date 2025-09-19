<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Config\ApiRoutes;
use Nkamuo\NotificationTrackerBundle\Repository\SlackMessageRepository;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SlackMessageRepository::class)]
#[ORM\Table(name: 'notification_tracker_slack_messages')]
#[ApiResource(
    shortName: 'SlackMessage',
    description: 'Slack message tracking',
    normalizationContext: ['groups' => ['message:read', 'slack:read']],
    denormalizationContext: ['groups' => ['message:write', 'slack:write']],
    routePrefix: ApiRoutes::BASE_PREFIX,
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

    public function getSubject(): ?string
    {
        // For Slack, use the message content as subject if available
        if ($this->content && $this->content->getBodyText()) {
            $text = $this->content->getBodyText();
            // Return first 50 characters as subject
            return strlen($text) > 50 ? substr($text, 0, 47) . '...' : $text;
        }
        
        return "Slack message to #{$this->channel}";
    }

}