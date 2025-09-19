# Doctrine Entity Definitions

## Base Message Entity

```php
<?php
// src/Entity/Communication/Message.php

namespace App\Entity\Communication;

use App\Repository\Communication\MessageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'communication_messages')]
#[ORM\Index(name: 'idx_message_status', columns: ['status'])]
#[ORM\Index(name: 'idx_message_sent_at', columns: ['sent_at'])]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap([
    'email' => EmailMessage::class,
    'sms' => SmsMessage::class,
    'slack' => SlackMessage::class,
    'telegram' => TelegramMessage::class,
])]
abstract class Message
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $transportName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $transportDsn = null;

    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'integer')]
    private int $retryCount = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $failureReason = null;

    #[ORM\ManyToOne(targetEntity: Notification::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Notification $notification = null;

    #[ORM\ManyToOne(targetEntity: MessageTemplate::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?MessageTemplate $template = null;

    #[ORM\OneToOne(targetEntity: MessageContent::class, mappedBy: 'message', cascade: ['persist', 'remove'])]
    private ?MessageContent $content = null;

    #[ORM\OneToMany(mappedBy: 'message', targetEntity: MessageRecipient::class, cascade: ['persist', 'remove'])]
    private Collection $recipients;

    #[ORM\OneToMany(mappedBy: 'message', targetEntity: MessageEvent::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['occurredAt' => 'DESC'])]
    private Collection $events;

    #[ORM\OneToMany(mappedBy: 'message', targetEntity: MessageAttachment::class, cascade: ['persist', 'remove'])]
    private Collection $attachments;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->recipients = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->attachments = new ArrayCollection();
    }

    // Getters and setters...
}
```

## Email Message Entity

```php
<?php
// src/Entity/Communication/EmailMessage.php

namespace App\Entity\Communication;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'communication_email_messages')]
class EmailMessage extends Message
{
    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column(length: 255)]
    private string $fromEmail;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fromName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $replyTo = null;

    #[ORM\Column(type: 'json')]
    private array $headers = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $messageId = null;

    #[ORM\Column(type: 'boolean')]
    private bool $trackOpens = true;

    #[ORM\Column(type: 'boolean')]
    private bool $trackClicks = true;

    // Getters and setters...
}
```

## Message Recipient Entity

```php
<?php
// src/Entity/Communication/MessageRecipient.php

namespace App\Entity\Communication;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'communication_message_recipients')]
#[ORM\Index(name: 'idx_recipient_message', columns: ['message_id'])]
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
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Message::class, inversedBy: 'recipients')]
    #[ORM\JoinColumn(nullable: false)]
    private Message $message;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_TO;

    #[ORM\Column(length: 255)]
    private string $address;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $openedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $clickedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $bouncedAt = null;

    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\OneToMany(mappedBy: 'recipient', targetEntity: MessageEvent::class)]
    private Collection $events;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->events = new ArrayCollection();
    }

    // Getters and setters...
}
```

## Message Event Entity

```php
<?php
// src/Entity/Communication/MessageEvent.php

namespace App\Entity\Communication;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'communication_message_events')]
#[ORM\Index(name: 'idx_event_message', columns: ['message_id'])]
#[ORM\Index(name: 'idx_event_type_date', columns: ['event_type', 'occurred_at'])]
class MessageEvent
{
    public const TYPE_QUEUED = 'queued';
    public const TYPE_SENT = 'sent';
    public const TYPE_DELIVERED = 'delivered';
    public const TYPE_OPENED = 'opened';
    public const TYPE_CLICKED = 'clicked';
    public const TYPE_BOUNCED = 'bounced';
    public const TYPE_COMPLAINED = 'complained';
    public const TYPE_UNSUBSCRIBED = 'unsubscribed';
    public const TYPE_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Message::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    private Message $message;

    #[ORM\ManyToOne(targetEntity: MessageRecipient::class, inversedBy: 'events')]
    private ?MessageRecipient $recipient = null;

    #[ORM\Column(length: 50)]
    private string $eventType;

    #[ORM\Column(type: 'json')]
    private array $eventData = [];

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    #[ORM\ManyToOne(targetEntity: WebhookPayload::class)]
    private ?WebhookPayload $webhookPayload = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->occurredAt = new \DateTimeImmutable();
    }

    // Getters and setters...
}
```

## Notification Entity

```php
<?php
// src/Entity/Communication/Notification.php

namespace App\Entity\Communication;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'communication_notifications')]
class Notification
{
    public const IMPORTANCE_LOW = 'low';
    public const IMPORTANCE_NORMAL = 'normal';
    public const IMPORTANCE_HIGH = 'high';
    public const IMPORTANCE_URGENT = 'urgent';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $type;

    #[ORM\Column(length: 20)]
    private string $importance = self::IMPORTANCE_NORMAL;

    #[ORM\Column(type: 'json')]
    private array $channels = [];

    #[ORM\Column(type: 'json')]
    private array $context = [];

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $userId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'notification', targetEntity: Message::class)]
    private Collection $messages;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->messages = new ArrayCollection();
    }

    // Getters and setters...
}
```