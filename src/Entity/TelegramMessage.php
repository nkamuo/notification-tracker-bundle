<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Repository\TelegramMessageRepository;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TelegramMessageRepository::class)]
#[ORM\Table(name: 'notification_tracker_telegram_messages')]
#[ApiResource(
    shortName: 'TelegramMessage',
    description: 'Telegram message tracking',
    normalizationContext: ['groups' => ['message:read', 'telegram:read']],
    denormalizationContext: ['groups' => ['message:write', 'telegram:write']],
)]
class TelegramMessage extends Message
{
    #[ORM\Column(length: 100)]
    #[Groups(['telegram:read', 'telegram:write'])]
    private string $chatId;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['telegram:read', 'telegram:write'])]
    private ?int $messageThreadId = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['telegram:read', 'telegram:write'])]
    private ?string $parseMode = 'HTML';

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['telegram:read', 'telegram:write'])]
    private bool $disableNotification = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['telegram:read', 'telegram:write'])]
    private ?array $replyMarkup = null;

    public function getType(): string
    {
        return 'telegram';
    }

    public function getChatId(): string
    {
        return $this->chatId;
    }

    public function setChatId(string $chatId): self
    {
        $this->chatId = $chatId;
        return $this;
    }

    public function getMessageThreadId(): ?int
    {
        return $this->messageThreadId;
    }

    public function setMessageThreadId(?int $messageThreadId): self
    {
        $this->messageThreadId = $messageThreadId;
        return $this;
    }

    public function getParseMode(): ?string
    {
        return $this->parseMode;
    }

    public function setParseMode(?string $parseMode): self
    {
        $this->parseMode = $parseMode;
        return $this;
    }

    public function isDisableNotification(): bool
    {
        return $this->disableNotification;
    }

    public function setDisableNotification(bool $disableNotification): self
    {
        $this->disableNotification = $disableNotification;
        return $this;
    }

    public function getReplyMarkup(): ?array
    {
        return $this->replyMarkup;
    }

    public function setReplyMarkup(?array $replyMarkup): self
    {
        $this->replyMarkup = $replyMarkup;
        return $this;
    }
}