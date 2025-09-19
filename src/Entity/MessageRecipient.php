<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Repository\MessageRecipientRepository;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: MessageRecipientRepository::class)]
#[ORM\Table(name: 'notification_tracker_message_recipients')]
#[ORM\Index(name: 'idx_nt_recipient_message', columns: ['message_id'])]
#[ORM\Index(name: 'idx_nt_recipient_status', columns: ['status'])]
class MessageRecipient
{
    public const TYPE_TO = 'to';
    public const TYPE_CC = 'cc';
    public const TYPE_BCC = 'bcc';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_OPENED = 'opened';
    public const STATUS_CLICKED = 'clicked';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_COMPLAINED = 'complained';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[Groups(['message:read'])]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Message::class, inversedBy: 'recipients')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Message $message;

    #[ORM\Column(length: 20)]
    #[Groups(['message:read'])]
    private string $type = self::TYPE_TO;

    #[ORM\Column(length: 255)]
    #[Groups(['message:read', 'message:list'])]
    private string $address;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['message:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    #[Groups(['message:read'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['message:read'])]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['message:read'])]
    private ?\DateTimeImmutable $openedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['message:read'])]
    private ?\DateTimeImmutable $clickedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['message:read'])]
    private ?\DateTimeImmutable $bouncedAt = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['message:read'])]
    private array $metadata = [];

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['message:read'])]
    private int $openCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['message:read'])]
    private int $clickCount = 0;

    #[ORM\OneToMany(targetEntity: MessageEvent::class, mappedBy: 'recipient')]
    private Collection $events;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->events = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function setMessage(Message $message): self
    {
        $this->message = $message;
        return $this;
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

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): self
    {
        $this->deliveredAt = $deliveredAt;
        return $this;
    }

    public function getOpenedAt(): ?\DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function setOpenedAt(?\DateTimeImmutable $openedAt): self
    {
        $this->openedAt = $openedAt;
        return $this;
    }

    public function getClickedAt(): ?\DateTimeImmutable
    {
        return $this->clickedAt;
    }

    public function setClickedAt(?\DateTimeImmutable $clickedAt): self
    {
        $this->clickedAt = $clickedAt;
        return $this;
    }

    public function getBouncedAt(): ?\DateTimeImmutable
    {
        return $this->bouncedAt;
    }

    public function setBouncedAt(?\DateTimeImmutable $bouncedAt): self
    {
        $this->bouncedAt = $bouncedAt;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getOpenCount(): int
    {
        return $this->openCount;
    }

    public function setOpenCount(int $openCount): self
    {
        $this->openCount = $openCount;
        return $this;
    }

    public function incrementOpenCount(): self
    {
        $this->openCount++;
        return $this;
    }

    public function getClickCount(): int
    {
        return $this->clickCount;
    }

    public function setClickCount(int $clickCount): self
    {
        $this->clickCount = $clickCount;
        return $this;
    }

    public function incrementClickCount(): self
    {
        $this->clickCount++;
        return $this;
    }

    public function getEvents(): Collection
    {
        return $this->events;
    }
}