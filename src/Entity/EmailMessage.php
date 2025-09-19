<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Repository\EmailMessageRepository;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EmailMessageRepository::class)]
#[ORM\Table(name: 'notification_tracker_email_messages')]
#[ApiResource(
    shortName: 'EmailMessage',
    description: 'Email message tracking',
    normalizationContext: ['groups' => ['message:read', 'email:read']],
    denormalizationContext: ['groups' => ['message:write', 'email:write']],
)]
class EmailMessage extends Message
{
    #[ORM\Column(length: 998)]
    #[Groups(['message:read', 'message:list', 'email:read', 'email:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 998)]
    private string $subject;

    #[ORM\Column(length: 255)]
    #[Groups(['message:read', 'email:read', 'email:write'])]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $fromEmail;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['message:read', 'email:read', 'email:write'])]
    private ?string $fromName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['message:read', 'email:read', 'email:write'])]
    #[Assert\Email]
    private ?string $replyTo = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['email:read'])]
    private array $headers = [];

    #[ORM\Column(length: 998, nullable: true)]
    #[Groups(['email:read'])]
    private ?string $messageId = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['email:read', 'email:write'])]
    private bool $trackOpens = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['email:read', 'email:write'])]
    private bool $trackClicks = true;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['email:read'])]
    private ?string $priority = null;

    public function getType(): string
    {
        return 'email';
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getFromEmail(): string
    {
        return $this->fromEmail;
    }

    public function setFromEmail(string $fromEmail): self
    {
        $this->fromEmail = $fromEmail;
        return $this;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function setFromName(?string $fromName): self
    {
        $this->fromName = $fromName;
        return $this;
    }

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    public function setReplyTo(?string $replyTo): self
    {
        $this->replyTo = $replyTo;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function setMessageId(?string $messageId): self
    {
        $this->messageId = $messageId;
        return $this;
    }

    public function isTrackOpens(): bool
    {
        return $this->trackOpens;
    }

    public function setTrackOpens(bool $trackOpens): self
    {
        $this->trackOpens = $trackOpens;
        return $this;
    }

    public function isTrackClicks(): bool
    {
        return $this->trackClicks;
    }

    public function setTrackClicks(bool $trackClicks): self
    {
        $this->trackClicks = $trackClicks;
        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(?string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }
}