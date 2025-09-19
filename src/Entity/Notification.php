<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Repository\NotificationRepository;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification_tracker_notifications')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Notification',
    description: 'Notification that can generate multiple messages',
    normalizationContext: ['groups' => ['notification:read']],
    denormalizationContext: ['groups' => ['notification:write']],
    operations: [
        new GetCollection(
            uriTemplate: '/notification-tracker/notifications',
            normalizationContext: ['groups' => ['notification:list']]
        ),
        new Get(
            uriTemplate: '/notification-tracker/notifications/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}']
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact', 'importance' => 'exact'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
class Notification
{
    public const IMPORTANCE_LOW = 'low';
    public const IMPORTANCE_NORMAL = 'normal';
    public const IMPORTANCE_HIGH = 'high';
    public const IMPORTANCE_URGENT = 'urgent';

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[Groups(['notification:read', 'notification:list', 'message:read'])]
    private Ulid $id;

    #[ORM\Column(length: 100)]
    #[Groups(['notification:read', 'notification:list', 'notification:write'])]
    private string $type;

    #[ORM\Column(length: 20)]
    #[Groups(['notification:read', 'notification:list', 'notification:write'])]
    private string $importance = self::IMPORTANCE_NORMAL;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['notification:read', 'notification:write'])]
    private array $channels = [];

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['notification:read', 'notification:write'])]
    private array $context = [];

    #[ORM\Column(type: 'ulid', nullable: true)]
    #[Groups(['notification:read', 'notification:write'])]
    private ?Ulid $userId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['notification:read', 'notification:write'])]
    private ?string $subject = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['notification:read', 'notification:list'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'notification', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['notification:read'])]
    private Collection $messages;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->messages = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getImportance(): string
    {
        return $this->importance;
    }

    public function setImportance(string $importance): self
    {
        $this->importance = $importance;
        return $this;
    }

    public function getChannels(): array
    {
        return $this->channels;
    }

    public function setChannels(array $channels): self
    {
        $this->channels = $channels;
        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function getUserId(): ?Ulid
    {
        return $this->userId;
    }

    public function setUserId(?Ulid $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setNotification($this);
        }
        return $this;
    }

    public function removeMessage(Message $message): self
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getNotification() === $this) {
                $message->setNotification(null);
            }
        }
        return $this;
    }
}