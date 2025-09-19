<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Repository\NotificationRepository;
use Nkamuo\NotificationTrackerBundle\Config\ApiRoutes;
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
            uriTemplate: ApiRoutes::NOTIFICATIONS,
            normalizationContext: ['groups' => ['notification:list']],
            paginationItemsPerPage: 20,
            paginationMaximumItemsPerPage: 100,
            paginationPartial: true
        ),
        new Get(
            uriTemplate: ApiRoutes::NOTIFICATIONS . '/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            normalizationContext: ['groups' => ['notification:detail']]
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact', 'importance' => 'exact', 'subject' => 'partial'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'type', 'importance', 'subject'])]
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
    #[Groups(['notification:read', 'notification:write', 'notification:list', 'notification:detail'])]
    private array $channels = [];

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['notification:read', 'notification:write', 'notification:detail'])]
    private array $context = [];

    #[ORM\Column(type: 'ulid', nullable: true)]
    #[Groups(['notification:read', 'notification:write', 'notification:list', 'notification:detail'])]
    private ?Ulid $userId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['notification:read', 'notification:write', 'notification:list', 'notification:detail'])]
    private ?string $subject = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['notification:read', 'notification:list'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'notification', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['notification:detail'])]
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

    /**
     * Get total message count
     */
    #[Groups(['notification:list', 'notification:detail'])]
    public function getTotalMessages(): int
    {
        return $this->messages->count();
    }

    /**
     * Get message statistics
     */
    #[Groups(['notification:list', 'notification:detail'])]
    public function getMessageStats(): array
    {
        $stats = [
            'total' => 0,
            'sent' => 0,
            'delivered' => 0,
            'failed' => 0,
            'pending' => 0,
            'queued' => 0,
            'cancelled' => 0,
        ];

        foreach ($this->messages as $message) {
            $stats['total']++;
            $status = $message->getStatus();
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $stats;
    }

    /**
     * Get recipient statistics
     */
    #[Groups(['notification:detail'])]
    public function getRecipientStats(): array
    {
        $stats = [
            'total_recipients' => 0,
            'unique_recipients' => 0,
            'total_opens' => 0,
            'total_clicks' => 0,
            'opened_recipients' => 0,
            'clicked_recipients' => 0,
        ];

        $uniqueAddresses = [];
        foreach ($this->messages as $message) {
            foreach ($message->getRecipients() as $recipient) {
                $stats['total_recipients']++;
                $uniqueAddresses[$recipient->getAddress()] = true;
                
                if ($recipient->getOpenedAt()) {
                    $stats['opened_recipients']++;
                }
                if ($recipient->getClickedAt()) {
                    $stats['clicked_recipients']++;
                }
                
                $stats['total_opens'] += $recipient->getOpenCount();
                $stats['total_clicks'] += $recipient->getClickCount();
            }
        }

        $stats['unique_recipients'] = count($uniqueAddresses);

        return $stats;
    }

    /**
     * Get engagement rates
     */
    #[Groups(['notification:list', 'notification:detail'])]
    public function getEngagementRates(): array
    {
        $recipientStats = $this->getRecipientStats();
        $messageStats = $this->getMessageStats();
        
        $delivered = $messageStats['delivered'] + $messageStats['sent'];
        $openedRecipients = $recipientStats['opened_recipients'];
        $clickedRecipients = $recipientStats['clicked_recipients'];
        
        return [
            'delivery_rate' => $messageStats['total'] > 0 ? round(($delivered / $messageStats['total']) * 100, 2) : 0,
            'open_rate' => $delivered > 0 ? round(($openedRecipients / $delivered) * 100, 2) : 0,
            'click_rate' => $openedRecipients > 0 ? round(($clickedRecipients / $openedRecipients) * 100, 2) : 0,
            'click_through_rate' => $delivered > 0 ? round(($clickedRecipients / $delivered) * 100, 2) : 0,
        ];
    }

    /**
     * Get the latest message date
     */
    #[Groups(['notification:list', 'notification:detail'])]
    public function getLatestMessageDate(): ?\DateTimeImmutable
    {
        $latest = null;
        foreach ($this->messages as $message) {
            if ($latest === null || $message->getCreatedAt() > $latest) {
                $latest = $message->getCreatedAt();
            }
        }
        return $latest;
    }
}