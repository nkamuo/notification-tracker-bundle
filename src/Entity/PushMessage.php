<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Repository\PushMessageRepository;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PushMessageRepository::class)]
#[ORM\Table(name: 'notification_tracker_push_messages')]
#[ApiResource(
    shortName: 'PushMessage',
    description: 'Push notification tracking',
    normalizationContext: ['groups' => ['message:read', 'push:read']],
    denormalizationContext: ['groups' => ['message:write', 'push:write']],
)]
class PushMessage extends Message
{
    #[ORM\Column(length: 255)]
    #[Groups(['push:read', 'push:write'])]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['push:read', 'push:write'])]
    private string $body;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['push:read', 'push:write'])]
    private ?string $icon = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['push:read', 'push:write'])]
    private ?string $image = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['push:read', 'push:write'])]
    private ?string $badge = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['push:read', 'push:write'])]
    private ?string $sound = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['push:read', 'push:write'])]
    private ?string $clickAction = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['push:read', 'push:write'])]
    private array $data = [];

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['push:read', 'push:write'])]
    private ?string $provider = null;

    public function getType(): string
    {
        return 'push';
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function setBadge(?string $badge): self
    {
        $this->badge = $badge;
        return $this;
    }

    public function getSound(): ?string
    {
        return $this->sound;
    }

    public function setSound(?string $sound): self
    {
        $this->sound = $sound;
        return $this;
    }

    public function getClickAction(): ?string
    {
        return $this->clickAction;
    }

    public function setClickAction(?string $clickAction): self
    {
        $this->clickAction = $clickAction;
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getSubject(): ?string
    {
        // For push notifications, use the title as subject
        return $this->title;
    }
}