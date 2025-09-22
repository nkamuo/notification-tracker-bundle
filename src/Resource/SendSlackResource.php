<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Nkamuo\NotificationTrackerBundle\Controller\Api\SendSlackController;

#[ApiResource(
    shortName: 'SendSlack',
    operations: [
        new Post(
            uriTemplate: '/send/slack',
            controller: SendSlackController::class,
            name: 'send_slack',
            description: 'Send a Slack message immediately or save as draft/schedule'
        )
    ]
)]
class SendSlackResource
{
    // This is a placeholder resource for API Platform
    // The actual logic is in SendSlackController
}
