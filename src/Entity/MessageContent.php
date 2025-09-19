<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'notification_tracker_message_contents')]
class MessageContent
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[Groups(['message:read'])]
    private Ulid $id;

    #[ORM\OneToOne(targetEntity: Message::class, inversedBy: 'content')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Message $message;

    #[ORM\Column(length: 50)]
    #[Groups(['message:read'])]
    private string $contentType = 'text/plain';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['message:read'])]
    private ?string $bodyText = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['message:read'])]
    private ?string $bodyHtml = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['message:read'])]
    private ?array $structuredData = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['message:read'])]
    private ?string $rawContent = null;

    public function __construct()
    {
        $this->id = new Ulid();
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

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): self
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function getBodyText(): ?string
    {
        return $this->bodyText;
    }

    public function setBodyText(?string $bodyText): self
    {
        $this->bodyText = $bodyText;
        return $this;
    }

    public function getBodyHtml(): ?string
    {
        return $this->bodyHtml;
    }

    public function setBodyHtml(?string $bodyHtml): self
    {
        $this->bodyHtml = $bodyHtml;
        return $this;
    }

    public function getStructuredData(): ?array
    {
        return $this->structuredData;
    }

    public function setStructuredData(?array $structuredData): self
    {
        $this->structuredData = $structuredData;
        return $this;
    }

    public function getRawContent(): ?string
    {
        return $this->rawContent;
    }

    public function setRawContent(?string $rawContent): self
    {
        $this->rawContent = $rawContent;
        return $this;
    }
}