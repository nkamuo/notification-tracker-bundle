<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'nt_label')]
#[ORM\Index(name: 'idx_nt_label_name', columns: ['name'])]
#[ORM\Index(name: 'idx_nt_label_color', columns: ['color'])]
#[ORM\Index(name: 'idx_nt_label_notification_count', columns: ['notification_count'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name'], message: 'A label with this name already exists.')]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['label:read']]),
        new GetCollection(normalizationContext: ['groups' => ['label:read', 'label:list']]),
        new Post(
            normalizationContext: ['groups' => ['label:read']],
            denormalizationContext: ['groups' => ['label:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['label:read']],
            denormalizationContext: ['groups' => ['label:write']]
        ),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['label:read']],
    denormalizationContext: ['groups' => ['label:write']],
    routePrefix: '/notification-tracker',
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'color' => 'exact',
    'description' => 'partial'
])]
#[ApiFilter(RangeFilter::class, properties: ['notificationCount'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'createdAt', 'notificationCount'])]
class Label
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[Groups(['label:read', 'label:list', 'message:read', 'notification:read'])]
    private Ulid $id;

    #[ORM\Column(length: 100, unique: true)]
    #[Groups(['label:read', 'label:write', 'label:list', 'message:read', 'notification:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    private string $name;

    #[ORM\Column(length: 7)]
    #[Groups(['label:read', 'label:write', 'label:list', 'message:read', 'notification:read'])]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^#[a-fA-F0-9]{6}$/', message: 'Color must be a valid hex color code')]
    private string $color = '#007bff';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['label:read', 'label:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['label:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['label:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['label:read', 'label:list'])]
    private int $notificationCount = 0;

    #[ORM\ManyToMany(targetEntity: Message::class, mappedBy: 'labels')]
    private Collection $messages;

    #[ORM\ManyToMany(targetEntity: Notification::class, mappedBy: 'labels')]
    private Collection $notifications;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->messages = new ArrayCollection();
        $this->notifications = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getNotificationCount(): int
    {
        return $this->notificationCount;
    }

    public function setNotificationCount(int $notificationCount): self
    {
        $this->notificationCount = $notificationCount;
        return $this;
    }

    public function updateCount(): self
    {
        $this->notificationCount = $this->notifications->count();
        return $this;
    }
    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
        }

        return $this;
    }

    public function removeMessage(Message $message): self
    {
        $this->messages->removeElement($message);

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $this->updateCount();
        }

        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        if ($this->notifications->removeElement($notification)) {
            $this->updateCount();
        }

        return $this;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PostLoad]
    public function postLoad(): void
    {
        // Sync the notification count with actual collection size
        // This helps maintain consistency if count gets out of sync
        $actualCount = $this->notifications->count();
        if ($this->notificationCount !== $actualCount) {
            $this->notificationCount = $actualCount;
        }
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
