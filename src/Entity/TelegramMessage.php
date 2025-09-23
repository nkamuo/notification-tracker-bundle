<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Config\ApiRoutes;
use Nkamuo\NotificationTrackerBundle\Repository\TelegramMessageRepository;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TelegramMessageRepository::class)]
#[ORM\Table(name: 'notification_tracker_telegram_messages')]
#[ApiResource(
    shortName: 'TelegramMessage',
    description: 'Telegram message tracking', operations: [
        new GetCollection(),
        new Get(),
        // new Put(
        //     controller: SendTelegramController::class,
        // ),
        // new Delete(),
    ],
    normalizationContext: ['groups' => ['message:read', 'telegram:read']],
    denormalizationContext: ['groups' => ['message:write', 'telegram:write']],
    routePrefix: ApiRoutes::BASE_PREFIX,
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

    public function getSubject(): ?string
    {
        // For Telegram, use the message content as subject if available
        if ($this->content && $this->content->getBodyText()) {
            $text = $this->content->getBodyText();
            // Return first 50 characters as subject
            return strlen($text) > 50 ? substr($text, 0, 47) . '...' : $text;
        }
        
        return "Telegram message to {$this->chatId}";
    }
}