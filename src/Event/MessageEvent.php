<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Event;

use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for message events
 */
abstract class MessageEvent extends Event
{
    public function __construct(
        private Message $message,
        private array $context = []
    ) {
    }

    public function getMessage(): Message
    {
        return $this->message;
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

    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }
}
