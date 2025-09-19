<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Repository\MessageAttachmentRepository;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageAttachmentRepository::class)]
#[ORM\Table(name: 'notification_tracker_message_attachments')]
#[ORM\Index(name: 'idx_nt_attachment_message', columns: ['message_id'])]
class MessageAttachment
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[Groups(['message:read', 'attachment:read'])]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Message::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Message $message;

    #[ORM\Column(length: 255)]
    #[Groups(['message:read', 'attachment:read'])]
    #[Assert\NotBlank]
    private string $filename;

    #[ORM\Column(length: 100)]
    #[Groups(['message:read', 'attachment:read'])]
    private string $contentType;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['message:read', 'attachment:read'])]
    private int $size;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['attachment:read'])]
    private ?string $path = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['attachment:read'])]
    private ?string $contentId = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['attachment:read'])]
    private bool $inline = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['attachment:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): self
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContentId(): ?string
    {
        return $this->contentId;
    }

    public function setContentId(?string $contentId): self
    {
        $this->contentId = $contentId;
        return $this;
    }

    public function isInline(): bool
    {
        return $this->inline;
    }

    public function setInline(bool $inline): self
    {
        $this->inline = $inline;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}