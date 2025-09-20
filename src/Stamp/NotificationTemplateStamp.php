<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

class NotificationTemplateStamp implements StampInterface
{
    public function __construct(
        private string $templateId,
        private ?string $templateName = null
    ) {}

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function getTemplateName(): ?string
    {
        return $this->templateName;
    }
}
