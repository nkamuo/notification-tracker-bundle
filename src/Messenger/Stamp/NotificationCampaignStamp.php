<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

class NotificationCampaignStamp implements StampInterface
{
    public function __construct(
        private string $campaignId,
        private ?string $campaignName = null
    ) {}

    public function getCampaignId(): string
    {
        return $this->campaignId;
    }

    public function getCampaignName(): ?string
    {
        return $this->campaignName;
    }
}
